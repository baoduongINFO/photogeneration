<?php 
	define('ARRAY_NEAREST_DEFAULT',    0);
	define('ARRAY_NEAREST_LOWER',      1);
	define('ARRAY_NEAREST_HIGHER',     2);
	
	
	$output = aspectRatio($_GET['width'], $_GET['height']);
	
	header("Access-Control-Allow-Origin: *");
	header("Content-Type: text/json");
	die(json_encode([
		'ratio' => $output
	]));
	
	function array_numeric_sorted_nearest($array, $value, $method = ARRAY_NEAREST_HIGHER) {    
	    $count = count($array);
	
	    if($count == 0) {
	        return null;
	    }    
	
	    $div_step               = 2;    
	    $index                  = ceil($count / $div_step);    
	    $best_index             = null;
	    $best_score             = null;
	    $direction              = null;    
	    $indexes_checked        = Array();
	
	    while(true) {        
	        if(isset($indexes_checked[$index])) {
	            break ;
	        }
	
	        $curr_key = $array[$index];
	        if($curr_key === null) {
	            break ;
	        }
	
	        $indexes_checked[$index] = true;
	
	        // perfect match, nothing else to do
	        if($curr_key == $value) {
	            return $curr_key;
	        }
	
	        $prev_key = $array[$index - 1];
	        $next_key = $array[$index + 1];
	
	        switch($method) {
	            default:
	            case ARRAY_NEAREST_DEFAULT:
	                $curr_score = abs($curr_key - $value);
	
	                $prev_score = $prev_key !== null ? abs($prev_key - $value) : null;
	                $next_score = $next_key !== null ? abs($next_key - $value) : null;
	
	                if($prev_score === null) {
	                    $direction = 1;                    
	                }else if ($next_score === null) {
	                    break 2;
	                }else{                    
	                    $direction = $next_score < $prev_score ? 1 : -1;                    
	                }
	                break;
	            case ARRAY_NEAREST_LOWER:
	                $curr_score = $curr_key - $value;
	                if($curr_score > 0) {
	                    $curr_score = null;
	                }else{
	                    $curr_score = abs($curr_score);
	                }
	
	                if($curr_score === null) {
	                    $direction = -1;
	                }else{
	                    $direction = 1;
	                }                
	                break;
	            case ARRAY_NEAREST_HIGHER:
	                $curr_score = $curr_key - $value;
	                if($curr_score < 0) {
	                    $curr_score = null;
	                }
	
	                if($curr_score === null) {
	                    $direction = 1;
	                }else{
	                    $direction = -1;
	                }  
	                break;
	        }
	
	        if(($curr_score !== null) && ($curr_score < $best_score) || ($best_score === null)) {
	            $best_index = $index;
	            $best_score = $curr_score;
	        }
	
	        $div_step *= 2;
	        $index += $direction * ceil($count / $div_step);
	    }
	
	    return $array[$best_index];
	}
	
    function aspectRatio($width, $height){
        $ratio = number_format($width / $height, 2);
        $test = Array(0.50, 0.62, 0.64, 0.66, 0.72, 0.75, 0.80, 0.83, 1, 1.20, 1.25, 1.33, 1.37, 1.41, 1.43, 1.50, 1.60, 1.66, 1.78, 2.00, 2.35);
        
		if(!is_float($ratio)){
		   $ratio = number_format($ratio, 2);
		}

		if(!in_array($ratio, $test)){
			$nearest = array_numeric_sorted_nearest($test, $ratio);
		}else{
			$nearest = $ratio;
		}
		
		
		if($ratio > end($test)){
			return "N/A";
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