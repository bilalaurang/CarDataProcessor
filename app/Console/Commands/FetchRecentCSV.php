<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
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
        $this->info("📅 Modified: {$latestFile['modifiedTime']}");
        
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
        $url = "https://www.googleapis.com/drive/v3/files?q={$query}&orderBy=modifiedTime%20desc&pageSize=50";
        
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
        // Create temporary file to work with League CSV
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $csv = Reader::createFromPath($tempFile, 'r');
            $csv->setHeaderOffset(0);
            
            $headers = $csv->getHeader();
            $this->info('📊 Header count: ' . count($headers));
            $this->info('📋 Headers: ' . implode(', ', array_slice($headers, 0, 5)) . '...');
            
            $records = iterator_to_array($csv->getRecords());
            $totalRows = count($records);
            $this->info("📈 Total rows to process: {$totalRows}");
            
            // CRITICAL FIX: Create progress bar and process with proper advancement
            $progressBar = $this->output->createProgressBar($totalRows);
            $progressBar->start();
            
            $processedCount = 0;
            
            foreach ($records as $offset => $record) {
                try {
                    // Process each record
                    $this->processRecord($record);
                    
                    $processedCount++;
                    
                    // CRITICAL FIX: Advance the progress bar for each processed record
                    $progressBar->advance();
                    
                    // CRITICAL FIX: Flush output buffer every 50 records to ensure progress updates
                    if ($processedCount % 50 === 0) {
                        $progressBar->display();
                        usleep(1000); // Small delay to prevent overwhelming the console
                    }
                    
                } catch (\Exception $e) {
                    $this->warn("⚠️ Error processing row {$offset}: " . $e->getMessage());
                    $progressBar->advance(); // Still advance even on error
                }
            }
            
            $progressBar->finish();
            $this->newLine();
            $this->info("✅ Successfully processed {$processedCount}/{$totalRows} records");
            
        } catch (\Exception $e) {
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
        // Extract data from record
        $data = [
            'ad_id' => $record['Ad ID'] ?? null,
            'activated_at' => $record['Activated At'] ?? null,
            'category_id' => $record['Category ID'] ?? null,
            'uuid' => $record['UUID'] ?? null,
            'has_whatsapp_number' => $record['Has Whatsapp Number'] ?? null,
            // Add other fields as needed
        ];
        
        // Skip if essential fields are missing
        if (empty($data['ad_id'])) {
            return;
        }
        
        // CRITICAL FIX: Use updateOrCreate instead of create to avoid duplicate key errors
        // This prevents the process from hanging on database constraint violations
        Car::updateOrCreate(
            ['ad_id' => $data['ad_id']], // Find by ad_id
            $data // Update with this data
        );
    }
}