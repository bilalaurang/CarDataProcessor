<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Car;
use Exception;

class FetchRecentCSV extends Command
{
    protected $signature = 'drive:fetch-recent-csv';
    protected $description = 'Fetch and process recent CSV file from Google Drive';

    public function handle()
    {
        $this->info('🚀 Starting CSV fetch from Google Drive...');
        
        // Get access token
        $this->info('🔑 Getting access token...');
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            $this->error('❌ Failed to get access token');
            return 1;
        }
        
        $this->info('✅ Access token obtained successfully');
        
        // Search for files
        $this->info('📂 Searching for files in folder...');
        $files = $this->listFiles($accessToken);
        
        if (empty($files)) {
            $this->error('❌ No files found in the folder');
            return 1;
        }
        
        // Get the most recent file
        $latestFile = $files[0];
        $this->info("📄 Found file: {$latestFile['name']}");
        $this->info("📅 Modified: " . ($latestFile['modifiedTime'] ?? 'Unknown'));
        
        // Download file
        $this->info('⬇️ Downloading file...');
        $csvContent = $this->downloadFile($latestFile['id'], $accessToken);
        
        if (!$csvContent) {
            $this->error('❌ Failed to download file');
            return 1;
        }
        
        $this->info('✅ File downloaded successfully (' . number_format(strlen($csvContent)) . ' bytes)');
        
        // Process CSV
        $this->info('🔄 Processing CSV data...');
        $this->processCSV($csvContent);
        
        $this->info('✅ CSV processing completed successfully!');
        return 0;
    }
    
    private function getAccessToken()
    {
        $keyPath = env('GOOGLE_DRIVE_SERVICE_ACCOUNT_KEY_PATH');
        
        if (!$keyPath || !file_exists($keyPath)) {
            $this->error('❌ Service account key file not found');
            return null;
        }
        
        $credentials = json_decode(file_get_contents($keyPath), true);
        
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = '';
        openssl_sign($base64Header . '.' . $base64Payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
        
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);
        
        if ($response->successful()) {
            return $response->json()['access_token'];
        }
        
        return null;
    }
    
    private function listFiles($accessToken)
    {
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $query = urlencode("'{$folderId}' in parents");
        $fields = urlencode('files(id,name,modifiedTime,mimeType)');
        $url = "https://www.googleapis.com/drive/v3/files?q={$query}&orderBy=modifiedTime%20desc&pageSize=50&fields={$fields}";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($url);
        
        if ($response->successful()) {
            return $response->json()['files'] ?? [];
        }
        
        return [];
    }
    
    private function downloadFile($fileId, $accessToken)
    {
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($url);
        
        if ($response->successful()) {
            return $response->body();
        }
        
        return null;
    }
    
    private function processCSV($csvContent)
    {
        // Create temporary file to work with built-in PHP CSV functions
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempFile, $csvContent);
        
        try {
            // Use PHP's built-in CSV functions instead of League\Csv for robust parsing
            $handle = fopen($tempFile, 'r');
            
            if (!$handle) {
                throw new Exception('Could not open CSV file');
            }
            
            // Read headers (first line)
            $headers = fgetcsv($handle, 0, ',');
            
            // Handle different delimiters if comma doesn't work
            if (!$headers || count($headers) < 2) {
                rewind($handle);
                $headers = fgetcsv($handle, 0, ';'); // Try semicolon
            }
            if (!$headers || count($headers) < 2) {
                rewind($handle);
                $headers = fgetcsv($handle, 0, "\t"); // Try tab
            }
            
            if (!$headers) {
                throw new Exception('Could not parse CSV headers');
            }
            
            // Clean headers (remove BOM, trim whitespace)
            $headers = array_map(function($header) {
                return trim(str_replace("\xEF\xBB\xBF", '', $header));
            }, $headers);
            
            $this->info('📊 Header count: ' . count($headers));
            $this->info('📋 Headers: ' . implode(', ', array_slice($headers, 0, 5)) . '...');
            
            // Count total rows first
            $totalRows = 0;
            while (fgetcsv($handle) !== FALSE) {
                $totalRows++;
            }
            
            $this->info("📈 Total rows to process: {$totalRows}");
            
            // Reset file pointer to start after headers
            rewind($handle);
            fgetcsv($handle); // Skip headers
            
            // Create progress bar
            $progressBar = $this->output->createProgressBar($totalRows);
            $progressBar->start();
            
            $processedCount = 0;
            $rowNumber = 1; // Start from 1 (after header)
            
            // Process each row
            while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        $progressBar->advance();
                        $rowNumber++;
                        continue;
                    }
                    
                    // Handle rows with different column counts
                    $rowData = [];
                    for ($i = 0; $i < count($headers); $i++) {
                        $rowData[$headers[$i]] = isset($row[$i]) ? trim($row[$i]) : null;
                    }
                    
                    // Process the record
                    $this->processRecord($rowData);
                    $processedCount++;
                    
                } catch (Exception $e) {
                    $this->warn("⚠️ Error processing row {$rowNumber}: " . $e->getMessage());
                }
                
                // Advance progress bar
                $progressBar->advance();
                
                // Update display every 50 records
                if ($processedCount % 50 === 0) {
                    $progressBar->display();
                    usleep(1000);
                }
                
                $rowNumber++;
            }
            
            fclose($handle);
            $progressBar->finish();
            $this->newLine();
            $this->info("✅ Successfully processed {$processedCount}/{$totalRows} records");
            
        } catch (Exception $e) {
            $this->error('❌ Error processing CSV: ' . $e->getMessage());
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
    
    private function processRecord($record)
    {
        // Extract data from record with error handling
        $data = [
            'ad_id' => $record['Ad ID'] ?? null,
            'activated_at' => !empty($record['Activated At']) ? $record['Activated At'] : null,
            'category_id' => $record['Category ID'] ?? null,
            'uuid' => $record['UUID'] ?? null,
            'has_whatsapp_number' => $record['Has Whatsapp Number'] ?? null,
            'seating_capacity' => $record['Seating Capacity'] ?? null,
            'engine_capacity' => $record['Engine Capacity'] ?? null,
            'target_market' => $record['Target Market'] ?? null,
            'is_premium' => $record['Is Premium'] ?? null,
            'make' => $record['Make'] ?? null,
            'model' => $record['Model'] ?? null,
            'trim' => $record['Trim'] ?? null,
            'url' => $record['Url'] ?? null,
            'title' => $record['Title'] ?? null,
            'seller_name' => $record['Dealer or seller name'] ?? null,
            'seller_phone_number' => $record['Seller phone number'] ?? null,
            'seller_type' => $record['Seller type'] ?? null,
            'posted_on' => !empty($record['Posted on']) ? $record['Posted on'] : null,
            'year' => $record['Year of the car'] ?? null,
            'price' => $record['Price'] ?? null,
            'kilometers' => $record['Kilometers'] ?? null,
            'color' => $record['Color'] ?? null,
            'doors' => $record['Doors'] ?? null,
            'cylinders' => $record['No. of Cylinders'] ?? null,
            'warranty' => $record['Warranty'] ?? null,
            'body_condition' => $record['Body condition'] ?? null,
            'mechanical_condition' => $record['Mechanical condition'] ?? null,
            'fuel_type' => $record['Fuel type'] ?? null,
            'regional_specs' => $record['Regional specs'] ?? null,
            'body_type' => $record['Body type'] ?? null,
            'steering_side' => $record['Steering side'] ?? null,
            'horsepower' => $record['Horsepower'] ?? null,
            'transmission_type' => $record['Transmission type'] ?? null,
            'location' => $record['Location of the car'] ?? null,
            'image_urls' => $record['Image urls'] ?? null,
        ];
        
        // Skip if essential fields are missing
        if (empty($data['ad_id'])) {
            return;
        }
        
        // Convert boolean-like strings
        $data['has_whatsapp_number'] = strtoupper($data['has_whatsapp_number']) === 'TRUE';
        $data['is_premium'] = strtoupper($data['is_premium']) === 'TRUE';
        
        // Clean numeric fields
        if (!empty($data['price'])) {
            $data['price'] = preg_replace('/[^0-9.]/', '', $data['price']);
        }
        if (!empty($data['kilometers'])) {
            $data['kilometers'] = preg_replace('/[^0-9.]/', '', $data['kilometers']);
        }
        if (!empty($data['year'])) {
            $data['year'] = preg_replace('/[^0-9]/', '', $data['year']);
        }
        
        // Use updateOrCreate to avoid duplicate key errors
        Car::updateOrCreate(
            ['ad_id' => $data['ad_id']],
            $data
        );
    }
}