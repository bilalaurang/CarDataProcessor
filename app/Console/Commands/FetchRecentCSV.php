<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Car;
use Exception;
use Illuminate\Support\Facades\DB;

class FetchRecentCSV extends Command
{
    protected $signature = 'drive:fetch-recent-csv';
    protected $description = 'Fetch and process recent CSV file from Google Drive';

    public function handle()
    {
        $this->info(' Starting CSV fetch from Google Drive...');
        
        // Get access token
        $this->info(' Getting access token...');
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            $this->error(' Failed to get access token');
            return 1;
        }
        
        $this->info(' Access token obtained successfully');
        
        // Search for files
        $this->info(' Searching for files in folder...');
        $files = $this->listFiles($accessToken);
        
        if (empty($files)) {
            $this->error(' No files found in the folder');
            return 1;
        }
        
        // Get the most recent file
        $latestFile = $files[0];
        $this->info(" Found file: {$latestFile['name']}");
        $this->info(" Modified: " . ($latestFile['modifiedTime'] ?? 'Unknown'));
        
        // Download file
        $this->info(' Downloading file...');
        $csvContent = $this->downloadFile($latestFile['id'], $accessToken);
        
        if (!$csvContent) {
            $this->error(' Failed to download file');
            return 1;
        }
        
        $this->info('File downloaded successfully (' . number_format(strlen($csvContent)) . ' bytes)');
        
        // Process CSV
        $this->info(' Processing CSV data...');
        $this->processCSV($csvContent);
        
        $this->info(' CSV processing completed successfully!');
        return 0;
    }
    
    private function getAccessToken()
    {
        $keyPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        
        if (!$keyPath || !file_exists($keyPath)) {
            $this->error(' Service account key file not found');
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
        try {
            // CRITICAL FIX: Use simple line-by-line processing
            $lines = explode("\n", $csvContent);
            $lines = array_values(array_filter($lines, function($line) {
                return trim($line) !== '';
            }));
            
            if (empty($lines)) {
                throw new Exception('CSV file appears to be empty');
            }
            
            // Get headers
            $headerLine = array_shift($lines);
            $headers = str_getcsv($headerLine);
            $headers = array_map(function($header) {
                return trim(str_replace("\xEF\xBB\xBF", '', $header));
            }, $headers);
            
            $this->info(' Header count: ' . count($headers));
            $this->info(' Headers: ' . implode(', ', array_slice($headers, 0, 5)) . '...');
            
            $totalRows = count($lines);
            $this->info(" Total rows to process: {$totalRows}");
            
            if ($totalRows === 0) {
                $this->warn(' No data rows found in CSV');
                return;
            }
            
            // CRITICAL FIX: Process in small batches to avoid hanging
            $batchSize = 10; // Process 10 rows at a time
            $batches = array_chunk($lines, $batchSize);
            
            $progressBar = $this->output->createProgressBar($totalRows);
            $progressBar->start();
            
            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($batches as $batchIndex => $batch) {
                $this->info("\n Processing batch " . ($batchIndex + 1) . "/" . count($batches));
                
                // Use database transactions for each batch
                DB::beginTransaction();
                
                try {
                    foreach ($batch as $line) {
                        // Parse row
                        $row = str_getcsv(trim($line));
                        
                        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                            $skippedCount++;
                            $progressBar->advance();
                            continue;
                        }
                        
                        // Create row data
                        $rowData = [];
                        for ($i = 0; $i < min(count($headers), count($row)); $i++) {
                            $rowData[$headers[$i]] = trim($row[$i]);
                        }
                        
                        // Process record with all columns
                        if (!empty($rowData['Ad ID'])) {
                            $data = [
                                'ad_id' => $rowData['Ad ID'],
                                'activated_at' => $rowData['Activated At'] ?? null,
                                'category_id' => $rowData['Category ID'] ?? null,
                                'uuid' => $rowData['UUID'] ?? null,
                                'has_whatsapp_number' => filter_var($rowData['Has Whatsapp Number'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                                'seating_capacity' => is_numeric($rowData['Seating Capacity'] ?? '') ? (int)$rowData['Seating Capacity'] : null,
                                'engine_capacity' => is_numeric($rowData['Engine Capacity'] ?? '') ? (int)$rowData['Engine Capacity'] : null,
                                'target_market' => $rowData['Target Market'] ?? null,
                                'is_premium' => filter_var($rowData['Is Premium'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                                'make' => $rowData['Make'] ?? null,
                                'model' => $rowData['Model'] ?? null,
                                'trim' => $rowData['Trim'] ?? null,
                                'url' => $rowData['Url'] ?? null,
                                'title' => $rowData['Title'] ?? null,
                                'seller_name' => $rowData['Dealer or seller name'] ?? null,
                                'seller_phone_number' => $rowData['Seller phone number'] ?? null,
                                'seller_type' => $rowData['Seller type'] ?? null,
                                'posted_on' => $rowData['Posted on'] ?? null,
                                'year' => is_numeric($rowData['Year of the car'] ?? '') ? (int)$rowData['Year of the car'] : null,
                                'price' => is_numeric($rowData['Price'] ?? '') ? $rowData['Price'] : null,
                                'kilometers' => is_numeric($rowData['Kilometers'] ?? '') ? (int)$rowData['Kilometers'] : null,
                                'color' => $rowData['Color'] ?? null,
                                'doors' => is_numeric($rowData['Doors'] ?? '') ? (int)$rowData['Doors'] : null,
                                'cylinders' => is_numeric($rowData['No. of Cylinders'] ?? '') ? (int)$rowData['No. of Cylinders'] : null,
                                'warranty' => $rowData['Warranty'] ?? null,
                                'body_condition' => $rowData['Body condition'] ?? null,
                                'mechanical_condition' => $rowData['Mechanical condition'] ?? null,
                                'fuel_type' => $rowData['Fuel type'] ?? null,
                                'regional_specs' => $rowData['Regional specs'] ?? null,
                                'body_type' => $rowData['Body type'] ?? null,
                                'steering_side' => $rowData['Steering side'] ?? null,
                                'horsepower' => is_numeric($rowData['Horsepower'] ?? '') ? (int)$rowData['Horsepower'] : null,
                                'transmission_type' => $rowData['Transmission type'] ?? null,
                                'location' => $rowData['Location of the car'] ?? null,
                                'image_urls' => $rowData['Image urls'] ?? null,
                            ];
                            DB::table('cars')->insertOrIgnore($data);
                            $processedCount++;
                        } else {
                            $skippedCount++;
                        }
                        
                        $progressBar->advance();
                    }
                    DB::commit();
                    $this->info("  Batch " . ($batchIndex + 1) . " completed");
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->warn("  Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage());
                    foreach ($batch as $line) {
                        $progressBar->advance();
                    }
                }
                $progressBar->display();
                usleep(100000);
            }
            $progressBar->finish();
            $this->newLine();
            $this->info(" Processing completed!");
            $this->info(" Successfully processed: {$processedCount} records");
            $this->info(" Skipped: {$skippedCount} empty/invalid rows");
            $this->info(" Errors: " . ($totalRows - $processedCount - $skippedCount) . " records failed");
            $this->info(" Total records in database: " . DB::table('cars')->count());
            $this->info(" CSV processing completed successfully!");
        } catch (Exception $e) {
            $this->error(' Error processing CSV: ' . $e->getMessage());
        }
    }
}