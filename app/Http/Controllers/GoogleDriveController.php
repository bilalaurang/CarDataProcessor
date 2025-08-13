<?php

namespace App\Http\Controllers;

use Google_Client;
use Google_Service_Drive;
use App\Models\Car;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    protected $driveService;

    public function __construct()
    {
        $client = new Google_Client();
        $client->setAuthConfig(env('GOOGLE_APPLICATION_CREDENTIALS'));
        $client->addScope(\Google\Service\Drive::DRIVE_READONLY);
        $this->driveService = new Google_Service_Drive($client);
    }

    public function readCSVFromDrive()
    {
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $files = $this->driveService->files->listFiles([
            'q' => "'$folderId' in parents and mimeType = 'text/csv'",
            'orderBy' => 'modifiedTime desc',
            'pageSize' => 10
        ]);

        $csvFiles = $files->getFiles();

        return response()->json($csvFiles);
    }

    public function storeCSVInDatabase($fileId)
    {
        $content = $this->driveService->files->get($fileId, ['alt' => 'media'])->getBody()->getContents();
        $rows = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($rows);

        foreach ($rows as $row) {
            if (!empty($row[0])) { // Skip empty rows
                $data = array_combine($headers, $row);
                Car::create([
                    'ad_id' => $data['Ad ID'] ?? null,
                    'activated_at' => $data['Activated At'] ?? null,
                    'category_id' => $data['Category ID'] ?? null,
                    'uuid' => $data['UUID'] ?? null,
                    'has_whatsapp_number' => $data['Has Whatsapp Number'] ?? false,
                    'seating_capacity' => $data['Seating Capacity'] ?? null,
                    'engine_capacity' => $data['Engine Capacity'] ?? null,
                    'target_market' => $data['Target Market'] ?? null,
                    'is_premium' => $data['Is Premium'] ?? false,
                    'make' => $data['Make'] ?? null,
                    'model' => $data['Model'] ?? null,
                    'trim' => $data['Trim'] ?? null,
                    'url' => $data['Url'] ?? null,
                    'title' => $data['Title'] ?? null,
                    'seller_name' => $data['Dealer or seller name'] ?? null,
                    'seller_phone_number' => $data['Seller phone number'] ?? null,
                    'seller_type' => $data['Seller type'] ?? null,
                    'posted_on' => $data['Posted on'] ?? null,
                    'year' => $data['Year of the car'] ?? null,
                    'price' => $data['Price'] ?? null,
                    'kilometers' => $data['Kilometers'] ?? null,
                    'color' => $data['Color'] ?? null,
                    'doors' => $data['Doors'] ?? null,
                    'cylinders' => $data['No. of Cylinders'] ?? null,
                    'warranty' => $data['Warranty'] ?? null,
                    'body_condition' => $data['Body condition'] ?? null,
                    'mechanical_condition' => $data['Mechanical condition'] ?? null,
                    'fuel_type' => $data['Fuel type'] ?? null,
                    'regional_specs' => $data['Regional specs'] ?? null,
                    'body_type' => $data['Body type'] ?? null,
                    'steering_side' => $data['Steering side'] ?? null,
                    'horsepower' => $data['Horsepower'] ?? null,
                    'transmission_type' => $data['Transmission type'] ?? null,
                    'location' => $data['Location of the car'] ?? null,
                    'image_urls' => $data['Image urls'] ?? null
                ]);
            }
        }

        return response()->json(['message' => 'CSV data stored in database']);
    }
}
