<?php

namespace App\Library;
use Illuminate\Support\Facades\Storage;

class PDFtoText{

  function getFile($file)
  {


       if(isset($file)) {
       
        //$file = clone $_FILES;
        require 'C:/xampp/htdocs/cloud/vendor/autoload.php';
     $target_dir = storage::path('public');
 
    $uploadOk = 1;
    $FileType = strtolower(pathinfo($file->getClientOriginalName(),PATHINFO_EXTENSION));
   
    $target_file = $target_dir . $file->getClientOriginalName();
    // Check file size
    if (filesize($file) > 5000000) {
        header('HTTP/1.0 403 Forbidden');
        echo "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    if($FileType != "pdf" && $FileType != "png" && $FileType != "jpg") {
        header('HTTP/1.0 403 Forbidden');
        echo "Sorry, please upload a pdf file";
        $uploadOk = 0;
    }
    if ($uploadOk == 1) {
   
        if (move_uploaded_file($file->getlinkTarget() , $target_file)) {
            
            $target_file = str_replace('public','public\\', $target_file);
            
            $out = $this->uploadToApi($file);
            return $out;
        } else {
            header('HTTP/1.0 403 Forbidden');
            echo "Sorry, there was an error uploading your file.";
        }
    } 
} else {
    header('HTTP/1.0 403 Forbidden');
    echo "Sorry, please upload a pdf file";
}



} 


// function generateRandomString($length = 10) {
//     $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
//     $charactersLength = strlen($characters);
//     $randomString = '';
//     for ($i = 0; $i < $length; $i++) {
//         $randomString .= $characters[rand(0, $charactersLength - 1)];
//     }
//     return $randomString;
// }


function uploadToApi($target_file){
    
    //dd(__DIR__.'/vendor/autoload.php');
    require 'C:/xampp/htdocs/cloud/vendor/autoload.php';
    $fileData = fopen($target_file , 'r');
 
    $client = new \GuzzleHttp\Client();

    $r = $client->request('POST', 'https://api.ocr.space/parse/image',[
        'headers' => ['apiKey' => '6292a4d84b88957'],
        'multipart' => [
            [
                'name' => 'file',
                'contents' => $fileData
            ]
        ]
    ], ['file' => $fileData]);
   
    return $response =  json_decode($r->getBody(),true);
    
   

 }


}//End Class