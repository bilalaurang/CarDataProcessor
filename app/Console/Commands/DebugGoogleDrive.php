<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google_Client;
use Google_Service_Drive;
use Google\Service\Drive;
use Exception;

class DebugGoogleDrive extends Command
{
    protected $signature = 'drive:debug';
    protected $description = 'Debug Google Drive connection and folder contents';

    public function handle()
    {
        $this->info('=== DEBUGGING GOOGLE DRIVE CONNECTION ===');
        
        // Check credential file
        $credentialPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        $this->info("Credential path from .env: " . $credentialPath);
        
        if (!file_exists($credentialPath)) {
            $this->error('Credential file not found at: ' . $credentialPath);
            return;
        }
        $this->info('✓ Credential file exists');

        // Initialize Google client
        try {
            $client = new Google_Client();
            $client->setAuthConfig($credentialPath);
            $client->addScope(Drive::DRIVE_READONLY);
            $service = new Google_Service_Drive($client);
            $this->info('✓ Google client initialized successfully');
        } catch (Exception $e) {
            $this->error('Failed to initialize Google client: ' . $e->getMessage());
            return;
        }

        // Check folder ID
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $this->info("Target folder ID: " . $folderId);
        
        if (empty($folderId)) {
            $this->error('Google Drive folder ID not set in .env');
            return;
        }

        // Test folder access
        try {
            $folder = $service->files->get($folderId);
            $this->info('✓ Successfully accessed folder: ' . $folder->name);
        } catch (Exception $e) {
            $this->error('Cannot access folder: ' . $e->getMessage());
            $this->error('This usually means:');
            $this->error('1. Wrong folder ID');
            $this->error('2. Service account lacks permission to access folder');
            return;
        }

        // List ALL files in folder (not just CSV/Sheets)
        $this->info("\n=== LISTING ALL FILES IN FOLDER ===");
        try {
            $allFiles = $service->files->listFiles([
                'q' => "'$folderId' in parents and trashed = false",
                'fields' => 'files(id,name,mimeType,modifiedTime,size)'
            ]);
            
            $files = $allFiles->getFiles();
            $this->info("Total files found: " . count($files));
            
            if (count($files) == 0) {
                $this->warn('No files found in this folder!');
                $this->warn('Make sure:');
                $this->warn('1. Files exist in the folder');
                $this->warn('2. Service account has access to the folder');
            } else {
                foreach ($files as $file) {
                    $this->info(sprintf(
                        "- %s (ID: %s, Type: %s, Modified: %s)",
                        $file->name,
                        $file->id,
                        $file->mimeType,
                        $file->modifiedTime
                    ));
                }
            }
        } catch (Exception $e) {
            $this->error('Error listing files: ' . $e->getMessage());
        }

        // Specifically look for CSV and Google Sheets
        $this->info("\n=== LOOKING FOR CSV/GOOGLE SHEETS ===");
        try {
            $csvFiles = $service->files->listFiles([
                'q' => "'$folderId' in parents and (mimeType = 'text/csv' or mimeType = 'application/vnd.google-apps.spreadsheet') and trashed = false",
                'orderBy' => 'modifiedTime desc',
                'fields' => 'files(id,name,mimeType,modifiedTime)'
            ]);
            
            $targetFiles = $csvFiles->getFiles();
            $this->info("CSV/Google Sheets found: " . count($targetFiles));
            
            foreach ($targetFiles as $file) {
                $this->info(sprintf(
                    "✓ %s (ID: %s, Type: %s, Modified: %s)",
                    $file->name,
                    $file->id,
                    $file->mimeType,
                    $file->modifiedTime
                ));
            }
            
        } catch (Exception $e) {
            $this->error('Error searching for CSV/Sheets: ' . $e->getMessage());
        }

        $this->info("\n=== DIAGNOSIS COMPLETE ===");
    }
}