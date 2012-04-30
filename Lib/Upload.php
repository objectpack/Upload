<?php

App::uses('File', 'Utility');

if(!defined('UPLOAD_ERR_EMPTY')){
	define('UPLOAD_ERR_EMPTY', 5);
}

/**
 * Upload class
 * 
 * @example
 * $upload = new Upload();
 * if(!$upload->setData('/my/path/to/data')))){
 * 		die($upload->error);
 * }
 * 
 */	
class Upload extends Object {
	
/**
 * Default options
 * 
 */
	protected $defaultOptions = array(
		'types' => array(				# List of types and corresponding configurations
			'default' => array()
		),
		'type' => 'default',			# Default type
		'extensions' => '*',			# List of allowed extensions
		'mimes' => '*',					# List if allowed mime types
		'folder' => TMP,				# Destination folder
		'maxSize' => 8,					# Maximum upload size
		'minSize' => 0,					# Minimum upload size
		'createFolder' => false,		# Recursivly create destination folder if not existant
		'required' => true,				# Is a file required
		'name' => null,					# Replace filename with this string (whitout extension)
		'lowName' => true,				# Enforce lowercase filename
		'slug' => true,					# Apply Inflector::slug() to basename
		'slugReplacement' => '_',		# Slug delimiter
		'onExistant' => 'suffix',		# What to do if target file exists error, overwrite, suffix
		'suffixLimit' => 100,			# Number of maximum trials to generate a suffix
		'suffixSeparator' => '-',		# String that will separate filename and suffix
		'lowExt' => true				# Enforce lowercase extension
	);
	
/**
 * Array containing information about the uploaded file
 * 
 */
	public $infos = array();
	
/**
 * Upload options
 * 
 * @see Upload::defaultOptions()
 */
	public $options = array();

/**
 * Last error message
 */	
	public $error = null;
	
/**
 * Class constructor
 * 
 * @todo Custom filters
 * 
 * @param array $options
 */
	public function __construct($options = array()){
		$this->defaultOptions = array_merge($this->defaultOptions, (array) Configure::read('Upload.options'));
		$this->reset($options);
	}
	
/**
 * Reset options to defaults
 * 
 * @param array $options	New options
 */
	public function reset($options = array()){
		$this->options = $this->defaultOptions;
		$this->infos = array();
		$this->setOptions($options);
	}

/**
 * Add options to the current set
 * 
 * @param array $options	New options
 */	
	public function setOptions($options = array()){
		$this->options = array_merge($this->options, (array) $options);
	}

/**
 * Sets the data path and checks upload validity
 * 
 * @param string $path	Path to the upload data using Set::extract()
 * @param array $options 
 */
	public function setData($path, $options = array()){
		
		$this->setOptions($options);
		
		/** 
		 * @todo access cakerequest data
		 */
		$infos = array(
			'name' => current((array) Set::extract('/data/name/'.$path, $_FILES)),
			'type' => current((array) Set::extract('/data/type/'.$path, $_FILES)),
			'tmp_name' => current((array) Set::extract('/data/tmp_name/'.$path, $_FILES)),
			'error' => current((array) Set::extract('/data/error/'.$path, $_FILES)),
			'size' => current((array) Set::extract('/data/size/'.$path, $_FILES))
		);
		
		$infos['original_name'] = $infos['name'];
		
		// Upload validity
		if (
			!is_array($infos)
			|| !array_reduce(array('name', 'type', 'tmp_name', 'error', 'size'), function($value, $key) use ($infos) {
				return $value && isset($infos[$key]);
			}, true)
		){
			$this->error = __d('Upload', 'Invalid upload');
			return false;
		}

		$this->infos = array_merge($infos, pathinfo($infos['name']));
				
		// Upload errors
		$errors = array(
			UPLOAD_ERR_INI_SIZE => __d('Upload', 'The uploaded file exceeds the limit (Server)'),
			UPLOAD_ERR_FORM_SIZE => __d('Upload', 'The uploaded file exceeds the limit (Form)'),
			UPLOAD_ERR_PARTIAL => __d('Upload', 'The uploaded file was only partially uploaded'),			
			UPLOAD_ERR_NO_TMP_DIR => __d('Upload', 'Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => __d('Upload', 'Failed to write file to disk'),
			UPLOAD_ERR_EXTENSION => __('Upload', 'A PHP extension stopped the file upload')
		);
		if($this->options['required']){
			$errors[UPLOAD_ERR_NO_FILE] = __d('Upload', 'No file was uploaded');
		}
		if(array_key_exists($this->data['error'], $errors)){
			$this->error = $errors[$this->data['error']];
			return false;
		}
		
		// No files were submited
		if($this->infos['error'] == UPLOAD_ERR_NO_FILE){
			return true;
		}
		
		// Preparation
		$file = new File($this->infos['tmp_name']);
		if(isset($this->options['type'])){
			if(!array_key_exists($this->options['type'], $this->options['types'])){
				$this->error = __d('Upload', 'Undefined upload type "%s"', $this->options['type']);
				return false;
			}
			$this->options = array_merge($this->options, $this->options['types'][$this->options['type']]);
		}
		
		// Mime type
		$this->infos['mime'] = $file->mime();
		if($this->options['mimes'] !== '*' && !in_array($this->infos['mime'], $this->options['mimes'])){
			$this->error = __d('Upload', 'Mime type "%s" not allowed', $this->infos['mime']);
			return false;
		}
		
		// Extension
		if($this->options['extensions'] !== '*' && !in_array(strtolower($this->infos['extension']), $this->options['extensions'])){
			$this->error = __d('Upload', 'Extension ".%s" not allowed', $this->infos['extension']);
			return false;
		}
		
		// File size
		$this->infos['size'] = $file->size();
		if($this->infos['size'] < (float) $this->options['minSize'] * 1024 * 1024 || $this->infos['size'] > (float) $this->options['maxSize'] * 1024 * 1024){
			$this->error = __d('Upload', 'The file size must be between %s Mb. and %s Mb. (actualiy %s Mb.)', round($this->options['minSize'], 2), round($this->options['maxSize'], 2), round($this->infos['size'], 2));
			return false;
		}
		
		// Destination directory
		$this->options['folder'] = rtrim(str_replace('/', DS, $this->options['folder']), DS);
		if(!is_dir($this->options['folder'])){
			if(
				!$this->options['createFolder']
				|| !$folder = new Folder()
				|| !$folder->create($options['folder'])
			){
				$this->error = __d('Upload', 'Destination folder does not exist');
				return false;
			}
		}
		
		// Destination directory writable
		if(!is_writable($this->options['folder'])){
			$this->error = __d('Upload', 'Destination folder is not writable by the server');
			return false;
		}
		
		// Generate name
		$name = $this->infos['filename'];
		if($this->options['name']){
			$this->infos['filename'] = $options['name'];
		}
		if($this->options['lowName']){
			$this->infos['filename'] = strtolower($this->infos['filename']);
		}
		if($this->options['slug']){
			$this->infos['filename'] = Inflector::slug($this->infos['filename'], $this->options['slugReplacement']);
		}
		if($this->options['lowExt']){
			$this->infos['extension'] = strtolower($this->infos['extension']);
		}
		$this->infos['name'] = $this->infos['extension'] == false ? $this->infos['filename'] : $this->infos['filename'].'.'.$this->infos['extension'];
		
		if(file_exists($this->options['folder'] . DS .$this->infos['name'])){
			switch($this->options['onExistant']){
				case 'error' : 
					$this->error = __d('Upload', 'Destination file already exists');
					return false;
				break;
				case 'suffix' : 
					$generated = $this->findAvailableName(
						array(
							'filename' => $this->infos['filename'],
							'extension' => $this->infos['extension']
						), 
						array(
							'folder' => $this->options['folder'],
							'limit' => $this->options['suffixLimit'],
							'separator' => $this->options['suffixSeparator']
						)
					);
					$this->infos = array_merge($this->infos, $generated);
					$this->infos['name'] = $this->infos['extension'] == false ? $this->infos['filename'] : $this->infos['filename'].'.'.$this->infos['extension'];
				break;
				default : 
					$this->error = __d('Upload', 'Configuration error');
					return false;
				break;
			}
		}
		
		return true;
	}
	
/**
 * Moves the uploaded file to its destination folder
 * 
 * @return bool True if file was correctly moved or if no file were submited
 */
	public function process(){
		
		// No files were submited
		if($this->infos['error'] == UPLOAD_ERR_NO_FILE){
			return true;
		}
								
		// Move file
		if(!move_uploaded_file($this->infos['tmp_name'], $this->options['folder'] . DS . $this->infos['name'])){
			$this->error = __d('Upload', 'Unable to move file on server');
			return false;
		}
		
		return true;
		
	}

/**
 * Finds an available file name in a folder incrementing a filename suffix
 * 
 * @example
 * // Folder structure :
 * // tmp
 * // - images
 * // -- image.jpg
 * // -- image-1.jpg
 * // -- image-2.jpg
 * // -- image-4.jpg
 * $fileName = Upload::findAvailableName('/tmp/image', 'jpg', array('folder' => '/images'));
 * echo $fileName;
 * // Output :
 * // image-3
 * 
 * @param string 	$name 		File base name (without extension)
 * @param string 	$ext 		File extension
 * @param array 	$options 	(folder)	folder		Search folder
 * 								(limit)		limit		Maximum increment 
 * 								(separator)	separator	Suffix separator
 * 								(suffix)	suffix		Start suffix at
 * 
 */
	public function findAvailableName($pathinfo, $options = array()) {
		
		if(is_string($pathinfo)){
			$pathinfo = pathinfo($pathinfo);
		}
		
		$options = array_merge(array(
			'folder' => TMP,
			'limit' => 1000,
			'separator' => '-',
			'suffix' => null
		), $options);
		
		extract($options);
		
		if($limit && $suffix > $limit) {
			$this->error = __d('Upload', 'Number of generated file name suffixes exceeds the limit');
			return false;
		}
		
		$folder = rtrim(str_replace('/', DS, $folder), DS);
		if(!is_dir($folder)){
			$this->error = __d('Upload', 'Destination folder does not exists');
			return false;
		}
		
		$pathinfo['suffixedName'] = $pathinfo['filename'];
		if($suffix){
			$pathinfo['suffixedName'] .= $separator.$suffix;
		}
		
		$pathinfo['basename'] = $pathinfo['suffixedName'];
		if(!empty($pathinfo['extension'])){
			$pathinfo['basename'] .= '.'.$pathinfo['extension'];
		}
		 
		if( !file_exists($folder . DS . $pathinfo['basename']) ){
			$pathinfo['filename'] = $pathinfo['suffixedName'];
			return $pathinfo;
		}
		
		$options['suffix'] += 1;
		
		return $this->findAvailableName($pathinfo, $options);
		
	}	
}