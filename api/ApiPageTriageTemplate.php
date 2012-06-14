<?php
/**
 * API module to fetch an arbitrary group of PageTriage backbone templates
 * for use in the JS application
 *
 * @ingroup API
 * @ingroup Extensions
 * @author Ian Baker
 */
class ApiPageTriageTemplate extends ApiBase {

	public function execute() {
		global $ptTemplatePath;

		// Get the API parameters and store them
		$opts = $this->extractRequestParams();
		$result = array();

		$view = $opts['view'];
		// validate
		if( preg_match( '/\W/', $view ) ) {
			$result = array( 'result' => 'error', 'errormsg' => 'Invalid view' );
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return true;
		}

		$templates = explode( '|', $opts['template'] );

		$contents = array();

		foreach ( array_unique( $templates ) as $template ) {
			// validate
			if( !preg_match( '/^\w+\.html$/', $template ) ) {
				$result = array( 'result' => 'error', 'errormsg' => 'Invalid template: ' . $template );
				$this->getResult()->addValue( null, $this->getModuleName(), $result );
				return true;
			}

			$localPath = $ptTemplatePath . '/ext.pageTriage.views.' . $view . '/ext.pageTriage.' . $template;
			if ( !file_exists( $localPath ) ) {
				$error = "template file not found: \"$localPath\"";
				$result = array( 'result' => 'error', 'errormsg' => $error );
				$this->getResult()->addValue( null, $this->getModuleName(), $result );
				return;
			}
			$contents[$template]= file_get_contents( $localPath );
		}

		// Output the results
		$result = array( 'result' => 'success', 'template' => $contents );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return array(
			'view' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'template' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			)
		);
	}

	public function getParamDescription() {
		return array(
			'view' => 'The PageTriage view for which you need the templates.',
			'template' => 'The template to fetch.  Separate multiple with the | character',
		);
	}

	public function getDescription() {
		return 'Fetch templates that are used within the PageTriage application.';
	}

	public function getExamples() {
		return array(
			'api.php?action=pagetriagetemplate&view=list&template=listItem.html',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
