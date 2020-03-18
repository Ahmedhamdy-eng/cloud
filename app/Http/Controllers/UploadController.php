<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use File;
use DB;
use ZipArchive;
use App\Library\VoiceRSS;
use Illuminate\Support\Str;
use App\Library\PDFtoText;
use App\library\PdfObjectBase;
use App\jobs\SendEmail;
use Carbon\carbon;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

class UploadController extends Controller
{

    var $bookFolder;
    var $bookName;
    var $folderPath;
    var $filesPath;

   public function index(){
	
   	return view('index');

   }

   public function store(Request $request){
   	

	$ext = $request->file->getClientOriginalExtension();
	$fileNameWithExt = $request->file->getClientOriginalName();

	$this->bookName = pathinfo($fileNameWithExt,PATHINFO_FILENAME);
	$this->bookFolder =sha1(time());
	$this->folderPath = storage_path('app/public').'/books/' . $this->bookFolder;
    $this->filesPath =  '/public/books/'.$this->bookFolder.'/';

	File::makeDirectory($this->folderPath, $mode = 0777, true, true);
	$this->convert_book_name_to_voice($this->bookName);
	Storage::put($this->filesPath.'name'.'.'.'txt', $this->convert_bookname_to_Ascii() );
	

	switch ($ext) {
		case 'pdf':
			 $fileContent = $this->read_pdf_file($request->file);
			break;
		

		case 'docx':

            $fileContent = $this->read_docx_file($request->file);
			break;

		case 'doc':
            $fileContent = $this->read_doc_file($request->file);
			break;

		default:
			# code...
			break;
	}



	 $asc  = $this->convertTextToAsci($fileContent);
	 $Cut_char = $this->charCut($asc);
	 $this->store_book($Cut_char);
     $path = $this->createZip();
     $this->deleteFiles();
     $folderPath = $this->bookFolder;
   
    return view('download')->with('implode',$folderPath);

   }//end function

    private function  deleteFiles(){
        File::deleteDirectory($this->folderPath);
    }
  # function to handle files and convert it to text.
   private function convertTextToAsci($text){
   //Delete Extra Things 
		 $delSpaces = str_replace([
		 '0','1','2','3','4','5','6','7','8','9',"\n","؛","–","»","»","\r","\t",']',
		 '[','~','@','&quot','﴿',')','+','*','َ','ّ','ً','ُ','ٌ','ِ','ٍ','ْ','=','﴾','(','?','«','«',':',
		 ';','/','،','-','!','\\','}','{','.','*','&','^','%','$','#','>','<','a','c','d','b','e',
		  'f','g','h','i','j','k','l','m','r','s','t','u','v','x','y','z'],'', $text);

		 $words = explode(' ',$delSpaces);
		 //cut all words
		 $strSplit = $this->str_split_unicode($delSpaces,1);
 		//count Words
		 $wordCount = str_word_count($delSpaces);
		 //convert char to char
		 $replace_to_char = $this->str_replace_char($strSplit);
 		//Convert Arabic To Brille
		 $fun = $this->str_replace_json($replace_to_char); 
 		//replace ' ' to E
		 $replace = str_replace(' ', 'E',$fun);
		//implode
		 $implode = implode($replace, ''); 
	     
				return $implode;
			
   }

   #this function to cut every 24 char and replace tthe extra char by space
   private function charCut($data){
   		
   	 for ($i = 24; $i < strlen($data); $i += 24) {
            $counter = 0;
            for ($j = $i; $data[$j] != 'E'; $j--) {
                $counter++;
            }
            if (($i - $j) > 0) {
                //replace string of  $sting[$j] to be str_repeat (' ',$i-$j +1)
                $data = substr_replace($data, str_repeat('E', $i - $j), $j, 0);
            }
        }
   			
       return $data;
      
   }
  # function to store books from 1.txt to the book lenght 
   private function store_book($files)
   { 
   			
             $start = 0;
			 $lenght= 360;
			 do {

			     $content=substr($files,$start,$lenght);

			  Storage::put($this->filesPath.(($start+$lenght)/$lenght).'.'.'txt', $content);

			 	$start+=$lenght;
 
			 } while ($start < strlen($files));
   }

    public function convert_bookname_to_Ascii()
    {
    	$bookNameToAscii = $this->convertTextToAsci($this->bookName);

    	return $bookNameToAscii;
    }
    #function to convert 
    public function convert_book_name_to_voice()
    {

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage::path('public\hajj-212111-616829371262.json'));

        $textToSpeechClient = new TextToSpeechClient();
        $input = new SynthesisInput();
        $input->setText($this->bookName);
        $voice = new VoiceSelectionParams();
        $voice->setLanguageCode('ar');
        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::MP3);

        $resp = $textToSpeechClient->synthesizeSpeech($input, $voice, $audioConfig);

       // file_put_contents('bookname.wav', $resp->getAudioContent() );
        Storage::put($this->filesPath.'bookname'.'.'.'wav', $resp->getAudioContent() );    
    }
	public function read_docx_file($file){
        $file_name = $file;
		
		$striped_content = '';
		
		$content = '';
		
		if(!$file_name || !file_exists($file_name)) return false;
		
		$zip = zip_open($file_name);
		
		if (!$zip || is_numeric($zip)) return true;
		
		while ($zip_entry = zip_read($zip)) {
		
		if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
		
		if (zip_entry_name($zip_entry) != "word/document.xml") continue;
		
		$content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
		
		zip_entry_close($zip_entry);
		
		}// end while
		
		zip_close($zip);
		$content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
		
		$content = str_replace('</w:r></w:p>', "\r\n", $content);

        $content = strip_tags($content);

       // dd($content);
		return $content;
	}

		

	public function read_pdf_file($file)
	{
		 
    $this->upload_object('hajj-212111.appspot.com',$this->bookName.'pdf', $file );
    $this->get_bucket_metadata('gs://hajj-212111.appspot.com/'.);
    $this->detect_pdf_gcs( 'gs://hajj-212111.appspot.com/'.$this->bookName.'pdf', 'gs://hajj-212111.appspot.com/'.$this->bookName.'pdf' );
    }//End Function

function get_bucket_metadata($bucketName)
{
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage::path('public\hajj-212111-616829371262.json'));
    $storage = new StorageClient();
    $bucket = $storage->bucket($bucketName);
    $info = $bucket->info();

    printf("Bucket Metadata: %s" . PHP_EOL, print_r($info));

}


function upload_object($bucketName, $objectName, $source)
{
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage::path('public\hajj-212111-616829371262.json'));
    $storage = new StorageClient();
    $file = fopen($source, 'r');
    $bucket = $storage->bucket($bucketName);
    $object = $bucket->upload($file, [
        'name' => $objectName
    ]);
    printf('Uploaded %s to gs://%s/%s' . PHP_EOL, basename($source), $bucketName, $objectName);
}

function detect_pdf_gcs($path, $output)
{
      putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage::path('public\hajj-212111-616829371262.json'));
    # select ocr feature
    $feature = (new Feature())
        ->setType(Type::DOCUMENT_TEXT_DETECTION);

    # set $path (file to OCR) as source
    $gcsSource = (new GcsSource())
        ->setUri($path);
    # supported mime_types are: 'application/pdf' and 'image/tiff'
    $mimeType = 'application/pdf';
    $inputConfig = (new InputConfig())
        ->setGcsSource($gcsSource)
        ->setMimeType($mimeType);

    # set $output as destination
    $gcsDestination = (new GcsDestination())
        ->setUri($output);
    # how many pages should be grouped into each json output file.
    $batchSize = 10;
    $outputConfig = (new OutputConfig())
        ->setGcsDestination($gcsDestination)
        ->setBatchSize($batchSize);

    # prepare request using configs set above
    $request = (new AsyncAnnotateFileRequest())
        ->setFeatures([$feature])
        ->setInputConfig($inputConfig)
        ->setOutputConfig($outputConfig);
    $requests = [$request];
    # make request
    $imageAnnotator = new ImageAnnotatorClient();
    $operation = $imageAnnotator->asyncBatchAnnotateFiles($requests);
    print('Waiting for operation to finish.' . PHP_EOL);
    $operation->pollUntilComplete();

    # once the request has completed and the output has been
    # written to GCS, we can list all the output files.
    preg_match('/^gs:\/\/([a-zA-Z0-9\._\-]+)\/?(\S+)?$/', $output, $match);
    $bucketName = $match[1];
    $prefix = isset($match[2]) ? $match[2] : '';

    $storage = new StorageClient();
    $bucket = $storage->bucket($bucketName);
    $options = ['prefix' => $prefix];
    $objects = $bucket->objects($options);
    # save first object for sample below
    $objects->next();
    $firstObject = $objects->current();
    
    $text = '';
   
    foreach ($objects as $object) {
        //dd($object->name() . PHP_EOL);
        print($object->name() . PHP_EOL);

            $jsonString = $object->downloadAsString();
            $firstBatch = new AnnotateFileResponse();
            $firstBatch->mergeFromJsonString($jsonString);
            
            # get annotation and print text
            foreach ($firstBatch->getResponses() as $response) {
                $annotation = $response->getFullTextAnnotation();
                /*dd($annotation);*/
                 $text .= $annotation->getText();
            }
    }

    # process the first output file from GCS.
    # since we specified batch_size=2, the first response contains
    # the first two pages of the input file.
    
    $imageAnnotator->close();

 $after_convert =iconv('windows-1256', 'UTF-8', $text);
    dd($after_convert);
}


	
	public function read_doc_file($userDoc){

    $fileHandle = fopen($userDoc, "r");
    $word_text = @fread($fileHandle, filesize($userDoc));
    $line = "";
    $tam = filesize($userDoc);
    $nulos = 0;
    $caracteres = 0;
    for($i=1536; $i<$tam; $i++)
    {
        $line .= $word_text[$i];

        if( $word_text[$i] == 0)
        {
            $nulos++;
        }
        else
        {
            $nulos=0;
            $caracteres++;
        }

        if( $nulos>1996)
        {   
            break;  
        }
    }

    //echo $caracteres;

    $lines = explode(chr(0x0D),$line);
    //$outtext = "<pre>";

    $outtext = "";
    foreach($lines as $thisline)
    {
        $tam = strlen($thisline);
        if( !$tam )
        {
            continue;
        }

        $new_line = ""; 
        for($i=0; $i<$tam; $i++)
        {
            $onechar = $thisline[$i];
            if( $onechar > chr(240) )
            {
                continue;
            }

            if( $onechar >= chr(0x20) )
            {
                $caracteres++;
                $new_line .= $onechar;
            }

            if( $onechar == chr(0x14) )
            {
                $new_line .= "</a>";
            }

            if( $onechar == chr(0x07) )
            {
                $new_line .= "\t";
                if( isset($thisline[$i+1]) )
                {
                    if( $thisline[$i+1] == chr(0x07) )
                    {
                        $new_line .= "\n";
                    }
                }
            }
        }
        //troca por hiperlink
        $new_line = str_replace("HYPERLINK" ,"<a href=",$new_line); 
        $new_line = str_replace("\o" ,">",$new_line); 
        $new_line .= "\n";

        //link de imagens
        $new_line = str_replace("INCLUDEPICTURE" ,"<br><img src=",$new_line); 
        $new_line = str_replace("\*" ,"><br>",$new_line); 
        $new_line = str_replace("MERGEFORMATINET" ,"",$new_line); 


        $outtext .= nl2br($new_line);
    $text =iconv('cp1252', 'UTF-8', utf8_encode($outtext));

    }
    dd($text)

 		
	}//end of function 

	function str_split_unicode($str, $l = 0) {

		if ($l > 0) {
			$ret = array();
			$len = mb_strlen($str, "UTF-8");
			for ($i = 0; $i < $len; $i += $l) {
				$ret[] = mb_substr($str, $i, $l, "UTF-8");
			}
			return $ret;
		}
		return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
	}
	function str_replace_json($subject){	
		$replaceThis = Array(
			
			'ا' => '',   
			'اَ' => '',
			'اً' => '',
			'اٌ' => '',
	      	'اٌ' => '',
			'آ' => '',
			'اِ' => '',
			'اّ' => '',

			'ب' => '',
			'بّ' => '',
			'بَ' => '',
			'بً' => '',
			'بُ' => '',
			'بٌ' => '',
			'بِ' => '',
			'بٍ' => '',
			'بْ' => '',

			'ت' => '',
			'تَ' => '',
			'تّ' => '',
			'تً' => '',
			'تُ' => '',
			'تٌ' => '',
			'تِ' => '',
			'تٍ' => '',
			'تْ' => '',

			'ث' => '9',
			'ثَ' => '9',
			'ثّ' => '9',
			'ثً' => '9',
			'ثُ' => '9',
			'ثٌ' => '9',
			'ثِ' => '9',
			'ثٍ' => '9',
			'ثْ' => '9',

			'ج' => '',
			'جَ' => '',
			'جّ' => '',
			'جً' => '',
			'جُ' => '',
			'جٌ' => '',
			'جِ' => '',
			'جٍ' => '',
			'جْ' => '',

			'ح' => '1',
			'حّ' => '1',
			'حَ' => '1',
			'حً' => '1',
			'حُ' => '1',
			'حٌ' => '1',
			'حِ' => '1',
			'حٍ' => '1',
			'حْ' => '1',

			'خ' => '-',
			'خّ' => '-',
			'خَ' => '-',
			'خً' => '-',
			'خُ' => '-',
			'خٌ' => '-',
			'خِ' => '-',
			'خٍ' => '-',
			'خْ' => '-',

			'د' => '',
			'دّ' => '',
			'دَ' => '',
			'دً' => '',
			'دُ' => '',
			'دٌ' => '',
			'دِ' => '',
			'دٍ' => '',
			'دْ' => '',

			'ذ' => '.',
			'ذّ' => '.',
			'ذَ' => '.',
			'ذً' => '.',
			'ذُ' => '.',
			'ذٌ' => '.',
			'ذِ' => '.',
			'ذٍ' => '.',
			'ذْ' => '.',

			'ر' => '',
			'رّ' => '',
			'رَ' => '',
			'رً' => '',
			'رُ' => '',
			'رٌ' => '',
			'رِ' => '',
			'رٍ' => '',
			'رْ' => '',

			'ز' => '5',
			'زّ' => '5',
			'زَ' => '5',
			'زً' => '5',
			'زُ' => '5',
			'زٌ' => '5',
			'زِ' => '5',
			'زٍ' => '5',
			'زْ' => '5',

			'س' => '',
			'سّ' => '',
			'سَ' => '',
			'سً' => '',
			'سُ' => '',
			'سٌ' => '',
			'سِِِِِ' => '',
			'سٍ' => '',
			'سْ' => '',


			'ش' => ')',
			'شّ' => ')',
			'شَ' => ')',
			'شً' => ')',
			'شُ' => ')',
			'شٌ' => ')',
			'شِ' => ')',
			'شٍ' => ')',
			'شْ' => ')',

			'ص' => '/',
			'صّ' => '/',
			'صَ' => '/',
			'صً' => '/',
			'صُ' => '/',
			'صٌ' => '/',
			'صِ' => '/',
			'صٍ' => '/',
			'صْ' => '/',

			'ض' => '+',
			'ضّ' => '+',
			'ضَ' => '+',
			'ضً' => '+',
			'ضُ' => '+',
			'ضٌ' => '+',
			'ضِ' => '+',
			'ضٍ' => '+',
			'ضْ' => '+',

			'ط' => '>',
			'طّ' => '>',
			'طَ' => '>',
			'طً' => '>',
			'طُ' => '>',
			'طٌ' => '>',
			'طِ' => '>',
			'طٍ' => '>',
			'طْ' => '>',

			'ظ' => '?',
			'ظّ' => '?',
			'ظَ' => '?',
			'ظً' => '?',
			'ظُ' => '?',
			'ظٌ' => '?',
			'ظِ' => '?',
			'ظٍ' => '?',
			'ظْ' => '?',


			'ع' => '7',
			'عّ' => '7',
			'عَ' => '7',
			'عً' => '7',
			'عُ' => '7',
			'عٌ' => '7',
			'عِ' => '7',
			'عٍ' => '7',
			'عْ' => '7',
			

			'غ' => '#',
			'غّ' => '#',
			'غَ' => '#',
			'غً' => '#',
			'غُ' => '#',
			'غٌ' => '#',
			'غِ' => '#',
			'غٍ' => '#',
			'غْ' => '#',

			'ف' => '',
			'فّ' => '',
			'فَ' => '',
			'فً' => '',
			'فُ' => '',
			'فٌ' => '',
			'فِ' => '',
			'فٍ' => '',
			'فْ' => '',

			'ق' => '',
			'قّ' => '',
			'قَ' => '',
			'قً' => '',
			'قُ' => '',
			'قٌ' => '',
			'قِ' => '',
			'قٍ' => '',
			'قْ' => '',

			'ك' => '',
			'كّ' => '',
			'كَ' => '',
			'كً' => '',
			'كُ' => '',
			'كٌ' => '',
			'كِ' => '',
			'كٍ' => '',
			'كْ' => '',

			'ل' => '',
			'ّل' => '',
			'َل' => '',
			'ًل' => '',
			'ُل' => '',
			'ٌل' => '',
			'ِل' => '',
			'ٍل' => '',
			'ْل' => '',
			'ْلَّ' => '',

			'م' => 'R',
			'مّ' => 'R',
			'مَ' => 'R',
			'مً' => 'R',
			'مُ' => 'R',
			'مٌ' => 'R',
			'مِ' => 'R',
			'مٍ' => 'R',
			'مْ' => 'R',

			'ن' => '',
			'ّن' => '',
			'َن' => '',
			'ًن' => '',
			'ُن' => '',
			'ٌن' => '',
			'ِن' => '',
			'ٍن' => '',
			'ْن' => '',
			'ْنَّ' => '',
			'ْنْ' => '',

			'ه' => '',
			'هّ' => '',
			'هَ' => '',
			'هً' => '',
			'هُ' => '',
			'هٌ' => '',
			'هِ' => '',
			'هٍ' => '',
			'هْ' => '',
			
			'و' => ':',
			'وّ' => ':',
			'وَ' => ':',
			'وً' => ':',
			'وُ' => ':',
			'وٌ' => ':',
			'وِ' => ':',
			'وٍ' => ':',
			'وْ' => ':',

			'ي' => 'B',
			'يّ' => 'B',
			'يَ' => 'B',
			'يً' => 'B',
			'يُ' => 'B',
			'يٌ' => 'B',
			'يِ' => 'B',
			'يٍ' => 'B',
			'يْ' => 'B',

			'لا' => "'",
			'لاّ' => "'",
			'لاَ' => "'",
			'لاً' => "'",
			'لاُ' => "'",
			'لاٌ' => "'",
			'لاِ' => "'",
			'لاٍ' => "'",
			'لاْ' => "'",

			'ىّ' => '',
			'ىَ' => '',
			'ىً' => '',
			'ىُ' => '',
			'ىٌ' => '',
			'ىِ' => '',
			'ىٍ' => '',
			'ىْ' => '',
			'ى' => '',

			'أ' => 'k',
			'أّ' => 'k',
			'أَ' => 'k',
			'أً' => 'k',
			'أُ' => 'k',
			'أٌ' => 'k',
			'أِ' => 'k',
			'أٍ' => 'k',
			'أْ' => 'k',

			'إ' => '(',
			'إّ' => '(',
			'إَ' => '(',
			'إً' => '(',
			'إُ' => '(',
			'إٌ' => '(',
			'إِ' => '(',
			'إٍ' => '(',
			'إْ' => '(',

			
			'آ' => '',

			'ء' => '',
			'ّء' => '',
			'َء' => '',
			'ًء' => '',
			'ُء' => '',
			'ٌء' => '',
			'ِء' => '',
			'ٍء' => '',
			'ْء' => '',

			'ؤ' => '3',
			'ّؤ' => '3',
			'َؤ' => '3',
			'ًؤ' => '3',
			'ُؤ' => '3',
			'ٌؤ' => '3',
			'ِؤ' => '3',
			'ٍؤ' => '3',
			'ْؤ' => '3',

			'ئ' => '=',
			'ّئ' => '=',
			'َئ' => '=',
			'ًئ' => '=',
			'ُئ' => '=',
			'ٌئ' => '=',
			'ِئ' => '=',
			'ٍئ' => '=',
			'ْئ' => '=',

			'ة' => '!',
			'ةّ' => '!',
			'ةَ' => '!',
			'ةً' => '!',
			'ةُ' => '!',
			'ةٌ' => '!',
			'ةِ' => '!',
			'ةٍ' => '!',
			'ةْ' => '!',

			'١' => '<',
			'٢' => '<',
			'٣' => '<D',
			'٤' => '<',
			'٥' => '<',
			'٦' => '<',
			'٧' => '<',
			'٨' => '<',
			'٩' => '<B',
			'٠' => '<',
			);			
	$replacedText = str_replace(array_keys($replaceThis), $replaceThis, $subject);		
    return $replacedText;
	}

	public function str_replace_char($subject){
		$replaceThis = array(
			'ا' => 'ا',   
			'اَ' => 'ا',
			'اً' => 'ا',
			'اٌ' => 'ا',
	      	'اٌ' => 'ا',
			'آ' => 'ا',
			'اِ' => 'ا',
			'اّ' => 'ا',

			'ب' => 'ب',
			'بّ' => 'ب',
			'بَ' => 'ب',
			'بً' => 'ب',
			'بُ' => 'ب',
			'بٌ' => 'ب',
			'بِ' => 'ب',
			'بٍ' => 'ب',
			'بْ' => 'ب',

			'ت' => 'ت',
			'تَ' => 'ت',
			'تّ' => 'ت',
			'تً' => 'ت',
			'تُ' => 'ت',
			'تٌ' => 'ت',
			'تِ' => 'ت',
			'تٍ' => 'ت',
			'تْ' => 'ت',

			'ث' => 'ث',
			'ثَ' => 'ث',
			'ثّ' => 'ث',
			'ثً' => 'ث',
			'ثُ' => 'ث',
			'ثٌ' => 'ث',
			'ثِ' => 'ث',
			'ثٍ' => 'ث',
			'ثْ' => 'ث',

			'ج' => 'ج',
			'جَ' => 'ج',
			'جّ' => 'ج',
			'جً' => 'ج',
			'جُ' => 'ج',
			'جٌ' => 'ج',
			'جِ' => 'ج',
			'جٍ' => 'ج',
			'جْ' => 'ج',

			'ح' => 'ح',
			'حّ' => 'ح',
			'حَ' => 'ح',
			'حً' => 'ح',
			'حُ' => 'ح',
			'حٌ' => 'ح',
			'حِ' => 'ح',
			'حٍ' => 'ح',
			'حْ' => 'ح',

			'خ' => 'خ',
			'خّ' => 'خ',
			'خَ' => 'خ',
			'خً' => 'خ',
			'خُ' => 'خ',
			'خٌ' => 'خ',
			'خِ' => 'خ',
			'خٍ' => 'خ',
			'خْ' => 'خ',

			'د' => 'د',
			'دّ' => 'د',
			'دَ' => 'د',
			'دً' => 'د',
			'دُ' => 'د',
			'دٌ' => 'د',
			'دِ' => 'د',
			'دٍ' => 'د',
			'دْ' => 'د',

			'ذ' => 'د',
			'ذّ' => 'د',
			'ذَ' => 'د',
			'ذً' => 'د',
			'ذُ' => 'د',
			'ذٌ' => 'د',
			'ذِ' => 'د',
			'ذٍ' => 'د',
			'ذْ' => 'د',

			'ر' => 'ر',
			'رّ' => 'ر',
			'رَ' => 'ر',
			'رً' => 'ر',
			'رُ' => 'ر',
			'رٌ' => 'ر',
			'رِ' => 'ر',
			'رٍ' => 'ر',
			'رْ' => 'ر',

			'ز' => 'ز',
			'زّ' => 'ز',
			'زَ' => 'ز',
			'زً' => 'ز',
			'زُ' => 'ز',
			'زٌ' => 'ز',
			'زِ' => 'ز',
			'زٍ' => 'ز',
			'زْ' => 'ز',

			'س' => 'س',
			'سّ' => 'س',
			'سَ' => 'س',
			'سً' => 'س',
			'سُ' => 'س',
			'سٌ' => 'س',
			'سِِِِِ' => 'س',
			'سٍ' => 'س',
			'سْ' => 'س',


			'ش' => 'ش',
			'شّ' => 'ش',
			'شَ' => 'ش',
			'شً' => 'ش',
			'شُ' => 'ش',
			'شٌ' => 'ش',
			'شِ' => 'ش',
			'شٍ' => 'ش',
			'شْ' => 'ش',

			'ص' => 'ص',
			'صّ' => 'ص',
			'صَ' => 'ص',
			'صً' => 'ص',
			'صُ' => 'ص',
			'صٌ' => 'ص',
			'صِ' => 'ص',
			'صٍ' => 'ص',
			'صْ' => 'ص',

			'ض' => 'ض',
			'ضّ' => 'ض',
			'ضَ' => 'ض',
			'ضً' => 'ض',
			'ضُ' => 'ض',
			'ضٌ' => 'ض',
			'ضِ' => 'ض',
			'ضٍ' => 'ض',
			'ضْ' => 'ض',

			'ط' => 'ط',
			'طّ' => 'ط',
			'طَ' => 'ط',
			'طً' => 'ط',
			'طُ' => 'ط',
			'طٌ' => 'ط',
			'طِ' => 'ط',
			'طٍ' => 'ط',
			'طْ' => 'ط',

			'ظ' => 'ظ',
			'ظّ' => 'ظ',
			'ظَ' => 'ظ',
			'ظً' => 'ظ',
			'ظُ' => 'ظ',
			'ظٌ' => 'ظ',
			'ظِ' => 'ظ',
			'ظٍ' => 'ظ',
			'ظْ' => 'ظ',


			'ع' => 'ع',
			'عّ' => 'ع',
			'عَ' => 'ع',
			'عً' => 'ع',
			'عُ' => 'ع',
			'عٌ' => 'ع',
			'عِ' => 'ع',
			'عٍ' => 'ع',
			'عْ' => 'ع',
			

			'غ' => 'غ',
			'غّ' => 'غ',
			'غَ' => 'غ',
			'غً' => 'غ',
			'غُ' => 'غ',
			'غٌ' => 'غ',
			'غِ' => 'غ',
			'غٍ' => 'غ',
			'غْ' => 'غ',

			'ف' => 'ف',
			'فّ' => 'ف',
			'فَ' => 'ف',
			'فً' => 'ف',
			'فُ' => 'ف',
			'فٌ' => 'ف',
			'فِ' => 'ف',
			'فٍ' => 'ف',
			'فْ' => 'ف',

			'ق' => 'ق',
			'قّ' => 'ق',
			'قَ' => 'ق',
			'قً' => 'ق',
			'قُ' => 'ق',
			'قٌ' => 'ق',
			'قِ' => 'ق',
			'قٍ' => 'ق',
			'قْ' => 'ق',

			'ك' => 'ك',
			'كّ' => 'ك',
			'كَ' => 'ك',
			'كً' => 'ك',
			'كُ' => 'ك',
			'كٌ' => 'ك',
			'كِ' => 'ك',
			'كٍ' => 'ك',
			'كْ' => 'ك',

			'ل' => 'ل',
			'ّل' => 'ل',
			'َل' => 'ل',
			'ًل' => 'ل',
			'ُل' => 'ل',
			'ٌل' => 'ل',
			'ِل' => 'ل',
			'ٍل' => 'ل',
			'ْل' => 'ل',
			'ْلَّ' => 'ل',

			'م' => 'م',
			'مّ' => 'م',
			'مَ' => 'م',
			'مً' => 'م',
			'مُ' => 'م',
			'مٌ' => 'م',
			'مِ' => 'م',
			'مٍ' => 'م',
			'مْ' => 'م',

			'ن' => 'ن',
			'ّن' => 'ن',
			'َن' => 'ن',
			'ًن' => 'ن',
			'ُن' => 'ن',
			'ٌن' => 'ن',
			'ِن' => 'ن',
			'ٍن' => 'ن',
			'ْن' => 'ن',
			'ْنَّ' => 'ن',
			'ْنْ' => 'ن',

			'ه' => 'ه',
			'هّ' => 'ه',
			'هَ' => 'ه',
			'هً' => 'ه',
			'هُ' => 'ه',
			'هٌ' => 'ه',
			'هِ' => 'ه',
			'هٍ' => 'ه',
			'هْ' => 'ه',
			
			'و' => 'و',
			'وّ' => 'و',
			'وَ' => 'و',
			'وً' => 'و',
			'وُ' => 'و',
			'وٌ' => 'و',
			'وِ' => 'و',
			'وٍ' => 'و',
			'وْ' => 'و',

			'ي' => 'ي',
			'يّ' => 'ي',
			'يَ' => 'ي',
			'يً' => 'ي',
			'يُ' => 'ي',
			'يٌ' => 'ي',
			'يِ' => 'ي',
			'يٍ' => 'ي',
			'يْ' => 'ي',

			'لا' => 'لا',
			'لاّ' => 'لا',
			'لاَ' => 'لا',
			'لاً' => 'لا',
			'لاُ' => 'لا',
			'لاٌ' => 'لا',
			'لاِ' => 'لا',
			'لاٍ' => 'لا',
			'لاْ' => 'لا',

			'ى' => 'ى',
			'ىّ' => 'ى',
			'ىَ' => 'ى',
			'ىً' => 'ى',
			'ىُ' => 'ى',
			'ىٌ' => 'ى',
			'ىِ' => 'ى',
			'ىٍ' => 'ى',
			'ىْ' => 'ى',
			

			'أ' => 'أ',
			'أّ' => 'أ',
			'أَ' => 'أ',
			'أً' => 'أ',
			'أُ' => 'أ',
			'أٌ' => 'أ',
			'أِ' => 'أ',
			'أٍ' => 'أ',
			'أْ' => 'أ',

			'إ' => 'إ',
			'إّ' => 'إ',
			'إَ' => 'إ',
			'إً' => 'إ',
			'إُ' => 'إ',
			'إٌ' => 'إ',
			'إِ' => 'إ',
			'إٍ' => 'إ',
			'إْ' => 'إ',

			
			'آ' => 'آ' ,

			'ء' => 'ء',
			'ّء' => 'ء',
			'َء' => 'ء',
			'ًء' => 'ء',
			'ُء' => 'ء',
			'ٌء' => 'ء',
			'ِء' => 'ء',
			'ٍء' => 'ء',
			'ْء' => 'ء',

			'ؤ' => 'ؤ',
			'ّؤ' => 'ؤ',
			'َؤ' => 'ؤ',
			'ًؤ' => 'ؤ',
			'ُؤ' => 'ؤ',
			'ٌؤ' => 'ؤ',
			'ِؤ' => 'ؤ',
			'ٍؤ' => 'ؤ',
			'ْؤ' => 'ؤ',

			'ئ' => 'ئ',
			'ّئ' => 'ئ',
			'َئ' => 'ئ',
			'ًئ' => 'ئ',
			'ُئ' => 'ئ',
			'ٌئ' => 'ئ',
			'ِئ' => 'ئ',
			'ٍئ' => 'ئ',
			'ْئ' => 'ئ',

			'ة' => 'ة',
			'ةّ' => 'ة',
			'ةَ' => 'ة',
			'ةً' => 'ة',
			'ةُ' => 'ة',
			'ةٌ' => 'ة',
			'ةِ' => 'ة',
			'ةٍ' => 'ة',
			'ةْ' => 'ة',
		);
			$replacedText = str_replace(array_keys($replaceThis), $replaceThis, $subject);
    return $replacedText;	
	}

	  public function createZip()
	    {
 			$zip = new ZipArchive();
		    $zip->open('storage/zip/'.$this->bookFolder.'.zip',  ZipArchive::CREATE);
		    $files= scandir($this->folderPath);
			unset($files[0],$files[1]);
		    foreach ($files as $file) {
		    	$download_file = storage::path('public/zip');
		         $zip->addFromString(basename($file),$download_file);
		    }
				 
			 if(  $zip->close() == true)
			return $this->bookFolder;   	
    	}


	

	
   
}//end of class
