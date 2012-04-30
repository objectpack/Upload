<?php

App::uses('Upload', 'Upload.Lib');

/**
 * Upload component
 * 
 * @example
 * <?php
 * 
 * class Images extends AppController {
 * 		var $components = array(
 * 			'Upload.Upload'
 * 		);
 * 		function upload(){
 * 			if($this->request->is('post')){
 * 				if($this->Upload->treat('/Image/file', array('type' => 'image'))){ # Set types in core.php
 * 					$this->Session->setFlash('Image successfuly uploaded');
 * 				}
 * 				else{
 * 					$this->Session->setFlash('Error : '.$this->Upload->error);
 * 				}
 * 			}
 * 		}
 * }
 * 
 * 
 */
class UploadComponent extends Component {
	
	public $Upload;
	
/**
 * Class constructor
 * 
 * @param object 	$collection
 * @param array 	$settings
 */
	public function __construct(ComponentCollection $collection, $settings = array()){
		$this->Upload = new Upload();
		$settings = array_merge($this->Upload->options, (array) $settings);
		parent::__construct($collection, $settings);
	}

/**
 * 
 */
    public function process($path, $settings = array()) {
		$this->Upload->reset(array_merge($this->settings, (array) $settings));
		if(
			!$this->Upload->setData($path)
			|| !$this->Upload->process()
		){
			$this->error = $this->Upload->error;
			return false;
		}
		return true;
    }
	
    public function findAvailableName($name, $options = array()) {
    	return $this->Upload->findAvailableName(pathinfo((string) $name), $options);
    }

}