<?php
define('ARRAY_NEAREST_DEFAULT', 0);
define('ARRAY_NEAREST_LOWER', 1);
define('ARRAY_NEAREST_HIGHER', 2);
class ImageMagick
{

    public $root_dir = __DIR__ . DIRECTORY_SEPARATOR;
    public $global_config;

    public $image;
    public $image_config;
    public $image_path;

    private $images;

    public function __construct()
    {
        $this->globalConfig = $this->configExists('configuration/global.json', true);
    }

    /**
     * $_GET request handler
     * @return [type] [description]
     */
    public function get($parameters)
    {
        $image_type = $parameters['image_material'];
        $image_ratio = $parameters['image_ratio'];
        $image_url = $parameters['image_url'];

        if (!isset($image_type) || !isset($image_ratio) || !isset($image_url)) {
            $this->throwError('missing_post_paramters');
        }

        if ($this->globalConfig[$image_type] && $this->globalConfig[$image_type][$image_ratio]) {
            $settings = reset($this->globalConfig[$image_type][$image_ratio]);
            $this->image_config = $this->configExists('configuration/' . ucfirst($image_type) . '/' . $settings['folder'] . '/settings.json', true);
            $this->image_path = 'configuration/' . ucfirst($image_type) . '/' . $settings['folder'];

            $this->image = $this->getImage($image_url);
            if ($this->image->statusCode != 200) {
                $this->throwError('could_not_download_image', [$image_url, $this->image->statusCode]);
            }
            $this->initGenerator();

            header("Content-Type: image/png");
            $this->images[0];
            $this->images[0]->flattenImages();

            echo $this->images[0];

        } else {
            $this->throwError('image_configuration_not_found', [$image_type, $image_ratio]);
        }

    }

    /**
     * $_POST request handler
     * @return [type] [description]
     */
    public function post($parameters)
    {

        $url = $parameters['url'];
        $print_url = $parameters['print_url'];
        $material = strtolower($parameters['material']);
        $slug = $parameters['slug'];
        $collection = $parameters['collection'];
        $photo_id = $parameters['photo_id'];
        $width = $parameters['width'];
        $height = $parameters['height'];
        $ratio = $this->aspectRatio($width, $height);

        switch ($material) {
            default:

                if ($ratio == 'N/A') {
                    $this->throwError('ratio_not_found', [$ratio, $width, $height]);
                }

                if ($this->globalConfig[$material] && $this->globalConfig[$material][$ratio]) {

                    $settings = $this->globalConfig[$material][$ratio];
                    $output_images = [];

                    $this->image = $this->getImage($print_url);
                    if ($this->image->statusCode != 200) {
                        $this->throwError('could_not_download_image', [$print_url, $this->image->statusCode]);
                    } else {
                        $this->image_clone = $this->image;
                    }

                    foreach ($settings as $key => $setting) {

                        $productType = ($key == 0 ? 'thumbnail' : 'beautyshot');
                        $this->image_config = $this->configExists('configuration/' . ucfirst($material) . '/' . $setting['folder'] . '/settings.json', true);
                        $this->image_path = 'configuration/' . ucfirst($material) . '/' . $setting['folder'];

						// Generate image
                        $this->initGenerator();
                        $this->images[0];
                        $this->images[0]->flattenImages();

                        $imageNamePath = $collection . '/' . ($productType == 'thumbnail' ? '' : 'beautyshot' . ($key > 1 ? $key : '') . '-') . $slug . '.png';
                        $imageNamePath = str_replace(' ', '', $imageNamePath);

                        if (!file_exists($this->root_dir . $collection)) {
                            mkdir($this->root_dir . $collection);
                        }

                        file_put_contents($this->root_dir . $imageNamePath, $this->images[0]);
						
						// Upload image to S3
                        $upload = $this->putS3($imageNamePath, $collection, 'images.fotocadeau.nl');
                        if ($upload === false) {
                            $this->throwError('could_not_upload_aws_s3', [$imageNamePath, $collection, 'images.fotocadeau.nl']);
                        }

                        $output_images[] = [
                            "type" => $productType,
                            "url" => "https://images.fotocadeau.nl/" . $imageNamePath,
                            "alt" => $slug
                        ];

                    }

					// Final step, Upload printfile to AWS S3.
                    file_put_contents($this->root_dir . 'temp/' . $photo_id . '.jpg', $this->image->content);
                    $this->putS3('temp/' . $photo_id . '.jpg', 'printfiles', 'images.fotocadeau.nl');
                    $output_images[] = [
                        "type" => 'print',
                        "url" => "https://images.fotocadeau.nl/printfiles/" . $photo_id . '.jpg',
                        "alt" => $slug
                    ];

                    echo json_encode($output_images, true);
                } else {

                    if (!$this->globalConfig[$material]) {
                        $this->throwError('material_configuration_not_found', [$material]);
                    } elseif (!$this->globalConfig[$material][$ratio]) {
                        $this->throwError('image_configuration_not_found', [$ratio]);
                    }

                }
                break;
        }
    }


    public function initGenerator()
    {

        // Check if our image exists
        foreach ($this->image_config['layers'] as $key => $value) {
            switch ($value['image_type']) {
                case 'image_background':
                    $this->background($key, $value);
                    break;
                case 'perspective_distortion_mask':
                    $this->perspective_distortion($key, $value);
                    break;
                case 'perspective_distortion_overlay':
                    $this->perspective_distortion_overlay($key, $value);
                    break;
                case 'image_resized_background':
                    $this->image_resized_background($key, $value);
                    break;
                case 'image_generate_canvas_perspective':
                    $this->image_generate_canvas_perspective($key, $value);
                    break;
                case 'image_mask_with_background':
                    $this->image_mask_with_background($key, $value);
                    break;
                case 'canvas_set_background':
                    $this->canvas_set_background($key, $value);
                    break;
                case 'image_zoom':
                    $this->image_zoom($key, $value);
                    break;
                default:
                    $this->throwError('settings_image_type_not_found', [$value['image_type'], $key]);
                    break;
            }
        }

    }

    public function background($key, $value)
    {
        $config = $this->image_config['layers'][$key];
        $image_location = $this->root_dir . $this->image_path . '/' . $config['file'];

        $image = new Imagick();
        $image->readImageBlob(file_get_contents($image_location));

        $this->images[$key] = $image;
    }


    public function image_zoom($key, $value)
    {

    }

    public function canvas_set_background($key, $value)
    {
        $config = $this->image_config['layers'][$key];
        $image_location = $this->root_dir . $this->image_path . '/' . $config['background'];
        $placement = $config['placement'];

		// Flatten images so we can add a background to it 
        $this->images[0];
        $this->images[0]->flattenImages();



        $new_background = new Imagick();
        $new_background->readImageBlob(file_get_contents($image_location));
        $new_background->compositeImage($this->images[0], Imagick::COMPOSITE_DEFAULT, $placement['x'], $placement['y']);

        header("Content-Type: image/png");
//         $this->images[0];
//         $this->images[0]->flattenImages();

// 		echo $new_background;
		
		
		//var_dump($config);
		
		
		//die();


        $this->images[0] = $new_background;
    }

    public function image_mask_with_background($key, $value)
    {
        $config = $this->image_config['layers'][$key];
        $image_location = $this->root_dir . $this->image_path . '/' . $config['file'];
        
        // Get mask image
        $mask = new Imagick();
        $mask_image = $image_location . $config['mask'];
        $mask->readImageBlob(file_get_contents($mask_image));
        
        
        // Set the base image
        $base = new Imagick();
        $base->readImageBlob($this->image->content);
        $base->setImageMatte(1);
        $base->adaptiveResizeImage($mask->getImageGeometry()['width'], $mask->getImageGeometry()['height']);
        $base->setImageFormat('png');
        
        // Add mask to image 
        $base->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0, Imagick::CHANNEL_ALPHA);
        
  
  
		// Create a background image 
        $background = new Imagick();
        $background->readImageBlob(file_get_contents($image_location . $config['background']));
  
		// Add base to background 
        $background->compositeImage($base, Imagick::COMPOSITE_DEFAULT, 0, 0);


        if (isset($this->images[$key - 1])) {
            $this->images[$key - 1] = $background;
        } else {
            $this->images[] = $background;
        }
    }


    public function image_resized_background($key, $value)
    {
		// Create new Imageick background (size defined in settings.json)
        $image = new Imagick();
        $config = (Object)$this->image_config['layers'][$key];

        $image->readImageBlob($this->image->content);
        $image->thumbnailImage($config->size['width'], $config->size['height'], false);
        $this->images[$key] = $image;
    }

    public function image_generate_canvas_perspective($key, $value)
    {
		// Convert our first image to our template
        $config = (Object)$this->image_config['layers'][$key];
        $file_path = $this->root_dir . 'temp/' . uniqid('tmp', true) . '.png';
        $this->images[0]->flattenImages();

        file_put_contents($file_path, $this->images[0]);

		// Apply 3DCanvas script to this image
        $angle = $config->rotation;
        $border_thickness = $config->border_width;

        $exec_command = './3Dcover -F 75,75 -s "' . $border_thickness . '" -a ' . $angle . ' -o 0 -b transparent ' . $file_path . ' ' . $file_path . '.out.png';
        $result = exec($exec_command, $result);

        $image = new Imagick();
        $image->readImageBlob(file_get_contents($file_path . '.out.png'));

        $this->images[$key - 1] = $image;
    }

    public function perspective_distortion($key, $value)
    {
        $image = new Imagick();
        $image->readImageBlob($this->image->content);
        $size = $image->getImageGeometry();


        $image->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
        $image->setImageMatte(true);


        $points = [];
        $controlPoints = (Object)$this->image_config['layers'][$key]['coordinates'];

        //POINT TOP LEFT
        $points[] = 0;      //Sx1
        $points[] = 0;      //Sy1
        $points[] = $controlPoints->topLeft['Dx'];      //Dx1
        $points[] = $controlPoints->topLeft['Dy'];      //Dy2


        //POINT TOP RIGHT
        $points[] = $size['width'];                     //Sx2
        $points[] = 0;                                  //Sy2
        $points[] = $controlPoints->topRight['Dx'];     //Dx2
        $points[] = $controlPoints->topRight['Dy'];     //Dy2

        //POINT BOTTOM LEFT
        $points[] = 0;                                  //Sx3
        $points[] = $size['height'];                    //Sy3
        $points[] = $controlPoints->bottomLeft['Dx'];   //Dx3
        $points[] = $controlPoints->bottomLeft['Dy'];   //Dy3

        //POINT BOTTOM RIGHT
        $points[] = $size['width'];                     //Sx4
        $points[] = $size['height'];                    //Sy4
        $points[] = $controlPoints->bottomRight['Dx'];  //Dx4
        $points[] = $controlPoints->bottomRight['Dy'];  //Dy4

        $image->distortImage(Imagick::DISTORTION_PERSPECTIVE, $points, true);
        $this->images[0]->compositeImage($image, Imagick::COMPOSITE_DEFAULT, $controlPoints->topLeft['Dx'], $controlPoints->topLeft['Dy']);
        $this->images[$key] = $image;
    }

    public function perspective_distortion_overlay($key, $value)
    {

        $image = new Imagick();
        $image->readImageBlob(file_get_contents($this->root_dir . $this->image_path . '/' . $value['file']));
        $image->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
        $image->setImageMatte(true);
        $this->images[0]->compositeImage($image, Imagick::COMPOSITE_DEFAULT, 0, 0);
        $this->images[$key] = $image;
    }


    private function configExists($config, $return = false, $errorOnFail = true)
    {
        $exists = file_exists($this->root_dir . $config);
        if ($errorOnFail == true && $exists == false) {
            $this->throwError('configuration_file_not_found', [$this->root_dir . $config]);
        }
        return ($exists == true ? ($return == true ? json_decode(file_get_contents($this->root_dir . $config), true) : true) : false);
    }

    public function getImage($imageSrc)
    {
        $ch = curl_init($imageSrc);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (object)['statusCode' => $httpcode, 'content' => $output];
    }

    public function throwError($errorType = null, $parameters = null)
    {
        switch ($errorType) {
            case 'configuration_file_not_found':
                $result = [
                    'success' => false,
                    'status' => 'configuration_file_not_found',
                    'message' => 'Configuration file "' . $parameters[0] . '" could not be found in'
                ];
                break;
            case 'missing_post_paramters':
                $result = [
                    'success' => false,
                    'status' => 'configuration_file_not_found',
                    'message' => 'missing paramters, required parameters: image_material, image_ratio, image_url'
                ];
                break;
            case 'image_configuration_not_found':
                $result = [
                    'success' => false,
                    'status' => 'image_configuration_not_found',
                    'message' => 'image configuration could not be found for ratio ' . $parameters[0]
                ];
                break;
            case 'material_configuration_not_found':

                if (strlen($parameters[0] > 0)) {
                    $message = 'Could not find material configuration for ' . $parameters[0];
                } else {
                    $message = 'Could not find configuration file for given material. Please make sure the configuration file exists and that the generation_name was set correctly';
                }

                $result = [
                    'success' => false,
                    'status' => 'material_configuration_not_found',
                    'message' => $message
                ];
                break;
            case 'settings_image_type_not_found':
                $result = [
                    'success' => false,
                    'status' => 'settings_image_type_not_found',
                    'message' => 'image_type [' . $parameters[1] . ']' . $parameters[0] . ' in settings.json is unknown'
                ];
                break;
            case 'could_not_download_image':
                $result = [
                    'success' => false,
                    'status' => 'could_not_download_image',
                    'message' => 'could not download source image ' . $parameters[0] . ' server replied with statusCode' . $parameters[1]
                ];
                break;
            case 'could_not_upload_aws_s3':
                $result = [
                    'success' => false,
                    'status' => 'could_not_upload_aws_s3',
                    'message' => 'could not upload file ' . $parameters[0] . ' to S3 bucket ' . $parameters[2] . '/' . $parameters[1]
                ];
                break;
            case 'ratio_not_found':
                $result = [
                    'success' => false,
                    'status' => 'ratio_not_found',
                    'message' => 'unknow ratio ' . $parameters[0] . ' for imageSize: ' . $parameters[1] . 'x' . $parameters[2]
                ];
                break;
            default:
                $result = [
                    'success' => false,
                    'status' => 'error_not_found',
                    'message' => 'error message DEFAULT could not be found, oops'
                ];
                break;
        }

        die(json_encode($result));
    }

    public function putS3($file, $uploadPath, $bucket)
    {


        if (!class_exists('S3')) require_once 'S3.class.php';

        if (!defined('awsAccessKey')) define('awsAccessKey', 'AKIAIA5SYNSDY3KCEF7Q');
        if (!defined('awsSecretKey')) define('awsSecretKey', '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');

        $uploadFile = dirname(__FILE__) . '/' . $file;
        $bucketName = $bucket;


        if (!file_exists($uploadFile) || !is_file($uploadFile))
            return false;

        if (!extension_loaded('curl') && !@dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll'))
            return false;


        $s3 = new S3(awsAccessKey, awsSecretKey);
        $s3->setSignatureVersion('v4');
        $s3->setRegion('eu-central-1');
        if ($s3->putObjectFile($uploadFile, $bucketName, $uploadPath . '/' . baseName($uploadFile), S3::ACL_PUBLIC_READ)) {
            return true;
        } else {
            return false;
        }
		
		
		
/*
		if (!class_exists('S3')) require_once 'S3.class.php';

		if (!defined('awsAccessKey')) define('awsAccessKey', 'AKIAIA5SYNSDY3KCEF7Q');
		if (!defined('awsSecretKey')) define('awsSecretKey', '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');
		
		$uploadFile = dirname(__FILE__). $file;
		$bucketName = $bucket;
		
		
		if (!file_exists($file) || !is_file($file))
			return ['success' => true, 'message' => 'No such file: ' . $file];
		
		$s3 = new S3(awsAccessKey, awsSecretKey);
		$s3->setSignatureVersion('v4');
		$s3->setRegion('eu-central-1');
		if ($s3->putObjectFile($uploadFile, $bucketName, baseName($uploadFile), S3::ACL_PUBLIC_READ)) {
			return ['success' => true, 'message' => 'File has been uploaded to AWS S3'];
		}else{
			return ['success' => false, 'message' => 'There was an error uploading the file ' . $file . ' to AWS S3'];
		}
         */
    }

    public function csvToConfig($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename))
            return false;

        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (!$header)
                    $header = $row;
                else
                    $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }

        $json_data = [];
        foreach ($data as $key) {
            $object = (Object)$key;
            $json_data[$object->Type][$object->Ratio][] = [
                'folder' => $object->Naam,
                'description' => $object->Beschrijving
            ];
        }
        return $json_data;
    }

    public function array_numeric_sorted_nearest($array, $value, $method = ARRAY_NEAREST_DEFAULT)
    {
        $count = count($array);

        if ($count == 0) {
            return null;
        }

        $div_step = 2;
        $index = ceil($count / $div_step);
        $best_index = null;
        $best_score = null;
        $direction = null;
        $indexes_checked = array();

        while (true) {
            if (isset($indexes_checked[$index])) {
                break;
            }

            $curr_key = $array[$index];
            if ($curr_key === null) {
                break;
            }

            $indexes_checked[$index] = true;
	
	        // perfect match, nothing else to do
            if ($curr_key == $value) {
                return $curr_key;
            }

            $prev_key = $array[$index - 1];
            $next_key = $array[$index + 1];

            switch ($method) {
                default:
                case ARRAY_NEAREST_DEFAULT:
                    $curr_score = abs($curr_key - $value);

                    $prev_score = $prev_key !== null ? abs($prev_key - $value) : null;
                    $next_score = $next_key !== null ? abs($next_key - $value) : null;

                    if ($prev_score === null) {
                        $direction = 1;
                    } else if ($next_score === null) {
                        break 2;
                    } else {
                        $direction = $next_score < $prev_score ? 1 : -1;
                    }
                    break;
                case ARRAY_NEAREST_LOWER:
                    $curr_score = $curr_key - $value;
                    if ($curr_score > 0) {
                        $curr_score = null;
                    } else {
                        $curr_score = abs($curr_score);
                    }

                    if ($curr_score === null) {
                        $direction = -1;
                    } else {
                        $direction = 1;
                    }
                    break;
                case ARRAY_NEAREST_HIGHER:
                    $curr_score = $curr_key - $value;
                    if ($curr_score < 0) {
                        $curr_score = null;
                    }

                    if ($curr_score === null) {
                        $direction = 1;
                    } else {
                        $direction = -1;
                    }
                    break;
            }

            if (($curr_score !== null) && ($curr_score < $best_score) || ($best_score === null)) {
                $best_index = $index;
                $best_score = $curr_score;
            }

            $div_step *= 2;
            $index += $direction * ceil($count / $div_step);
        }

        return $array[$best_index];
    }

    public function aspectRatio($width, $height)
    {
        $ratio = number_format($width / $height, 2);
        $test = array(0.50, 0.62, 0.64, 0.66, 0.72, 0.75, 0.80, 0.83, 1, 1.20, 1.25, 1.33, 1.37, 1.41, 1.43, 1.50, 1.60, 1.66, 1.78, 2.00, 2.35);

        if (!is_float($ratio)) {
            $ratio = number_format($ratio, 2);
        }

        if (!in_array($ratio, $test)) {
            $nearest = $this->array_numeric_sorted_nearest($test, $ratio);
        } else {
            $nearest = $ratio;
        }

        switch ($nearest) {
            case '0.83':
                return "5:6";
                break;
            case '0.80':
                return "4:5";
                break;
            case '0.75':
                return "3:4";
                break;
            case '0.72':
                return "8:11";
                break;
            case '0.50':
                return "1:2";
                break;
            case '0.60':
                return "3:5";
                break;
            case '0.56':
                return "9:16";
                break;
            case '0.66':
                return "2:3";
                break;
            case '0.64':
                return "9:14";
                break;
            case '0.62':
                return "10:16";
                break;
            case '1':
                return "1:1";
                break;
            case '1.20':
                return "6:5";
                break;
            case '1.25':
                return "5:4";
                break;
            case '1.33':
                return "4:3";
                break;
            case '1.37':
                return "11:8";
            case '1.50':
                return "3:2";
                break;
            case '1.56':
                return "14:9";
                break;
            case '1.6':
                return "16:10";
                break;
            case '1.66':
                return "5:3";
                break;
            case '1.78':
                return "16:9";
                break;
            case '2.00':
                return "2:1";
                break;
            case '2.35':
                return "21:9";
                break;
            default:
                return "N/A";
                break;
        }
    }
}
