<?php

App::uses('Upload', 'Upload.Lib');

class UploadBehavior extends ModelBehavior {
	
/**
 * Before save callback
 *
 * @param object $model Model using this behavior
 * @return mixed False if the operation should abort. Any other result will continue.
 * @access public
 */
	public function beforeSave($Model) {
		$Upload = new Upload();
		foreach($this->settings[$Model->alias] as $field => $config){
			$Upload->reset($config);
			if(
				!$Upload->setData($Model->alias.'/'.$field)
				|| !$Upload->process()
			){
				$Model->invalidate($field, $Upload->error);
				return false;
			}
			$Model->data[$Model->alias][$field] = $Upload->data['name'];
		}
		return true;
	}
	
/**
 * BeforeDelete Callback : Delete the files
 *
 * @param unknown_type $model
 * @return unknown
 */
	function beforeDelete($Model){
		$settings = $this->settings[$Model->alias];
		$Upload = new Upload();
		foreach($this->settings[$Model->alias] as $field => $config){
			$Upload->reset($config);
			$value = $Model->field($field);
			if(!empty($value)){
				$file = $Upload->options['folder'].DS.$value;
				if(
					!is_file($file)
					|| !unlink($file)
				){
					trigger_error(__d('Upload', 'File %s does not exists', $file), E_USER_WARNING);
					return false;
				}
			}
		}
		return true;
	}
	
}

