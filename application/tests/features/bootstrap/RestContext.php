<?php
use Behat\Behat\Context\BehatContext;
use Symfony\Component\Yaml\Yaml;

/**
 * Rest context.
 */
class RestContext extends BehatContext
{

	private $_restObject        = null;
	private $_restObjectType    = null;
	private $_restObjectMethod  = 'get';
	private $_client            = null;
	private $_response          = null;
	private $_requestUrl        = null;
	private $_apiUrl           = 'api/v2';

	private $_parameters			= array();

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 */
	public function __construct(array $parameters)
	{
		// Initialize your context here

		$this->_restObject = new stdClass();
		$this->_parameters = $parameters;
		
		$base_url = $this->getParameter('base_url');
		$proxy_url = $this->getParameter('proxy_url');
		
		$options = array();
		if($proxy_url)
		{
			$options['curl.options'] = array(CURLOPT_PROXY => $proxy_url);
		}
		
		$this->_client = new Guzzle\Service\Client($base_url, $options);
	}

	public function getParameter($name)
	{
		if (count($this->_parameters) === 0) {


			throw new \Exception('Parameters not loaded!');
		} else {

			$parameters = $this->_parameters;
			return (isset($parameters[$name])) ? $parameters[$name] : null;
		}
	}

	/**
	 * @Given /^that I want to make a new "([^"]*)"$/
	 */
	public function thatIWantToMakeANew($objectType)
	{
		$this->_restObjectType   = ucwords(strtolower($objectType));
		$this->_restObjectMethod = 'post';
	}

	/**
	 * @Given /^that I want to update a "([^"]*)"$/
	 */
	public function thatIWantToUpdateA($objectType)
	{
		$this->_restObjectType   = ucwords(strtolower($objectType));
		$this->_restObjectMethod = 'put';
	}

	/**
	 * @Given /^that I want to find a "([^"]*)"$/
	 */
	public function thatIWantToFindA($objectType)
	{
		$this->_restObjectType   = ucwords(strtolower($objectType));
		$this->_restObjectMethod = 'get';
	}

	/**
	 * @Given /^that I want to get all "([^"]*)"$/
	 */
	public function thatIWantToGetAll($objectType)
	{
		$this->_restObjectType   = ucwords(strtolower($objectType));
		$this->_restObjectMethod = 'get';
	}

	/**
	 * @Given /^that I want to delete a "([^"]*)"$/
	 */
	public function thatIWantToDeleteA($objectType)
	{
		$this->_restObjectType   = ucwords(strtolower($objectType));
		$this->_restObjectMethod = 'delete';
	}

	/**
	 * @Given /^that the request "([^"]*)" is:$/
	 */
	public function thatTheRequestIs($propertyName, $propertyValue)
	{
		$this->_restObject->$propertyName = $propertyValue;
	}

	/**
	 * @Given /^that its "([^"]*)" is "([^"]*)"$/
	 */
	public function thatItsIs($propertyName, $propertyValue)
	{
		$this->_restObject->$propertyName = $propertyValue;
	}
		
	/**
	 * @When /^I request "([^"]*)"$/
	 */
	public function iRequest($pageUrl)
	{
		$this->_requestUrl 	= $this->_apiUrl.$pageUrl;

		switch (strtoupper($this->_restObjectMethod)) {
			case 'GET':
				$request = (array)$this->_restObject;
				$id = ( isset($request['id']) ) ? $request['id'] : '';
				$query_string = ( isset($request['query string']) ) ? '?'.$request['query string'] : '';
				$http_request = $this->_client
					->get($this->_requestUrl.'/'.$id.$query_string);
				break;
			case 'POST':
				$postFields = (array)$this->_restObject;
				$http_request = $this->_client
					->post($this->_requestUrl,null,$postFields['data']);
				break;
			case 'PUT':
				$request = (array)$this->_restObject;
				$id = ( isset($request['id']) ) ? $request['id'] : '';
				$http_request = $this->_client
					->put($this->_requestUrl.'/'.$id,null,$request['data']);
				break;
			case 'DELETE':
				$request = (array)$this->_restObject;
				$id = ( isset($request['id']) ) ? $request['id'] : '';
				$http_request = $this->_client
					->delete($this->_requestUrl.'/'.$id);
				break;
		}

		try {
			$http_request->send();
		} catch (Guzzle\Http\Exception\BadResponseException $e) {
			// Don't care.
			// 4xx and 5xx statuses are valid error responses
		}
		
		// Get response object
		$this->_response = $http_request->getResponse();
		
		// Create fake response object if Guzzle doesn't give us one
		if (! $this->_response instanceof Guzzle\Http\Message\Response)
		{
			$this->_response = new Guzzle\Http\Message\Response(null, null, null);
		}
	}

	/**
	 * @Then /^the response is JSON$/
	 */
	public function theResponseIsJson()
	{
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		// Check for NULL not empty - since [] and {} will be empty but valid
		if ($data === NULL) {
			
			// Get further error info
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					$error = 'No errors';
				break;
				case JSON_ERROR_DEPTH:
					$error = 'Maximum stack depth exceeded';
				break;
				case JSON_ERROR_STATE_MISMATCH:
					$error = 'Underflow or the modes mismatch';
				break;
				case JSON_ERROR_CTRL_CHAR:
					$error = 'Unexpected control character found';
				break;
				case JSON_ERROR_SYNTAX:
					$error = 'Syntax error, malformed JSON';
				break;
				case JSON_ERROR_UTF8:
					$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
				default:
					$error = 'Unknown error';
				break;
			}
			
			throw new Exception("Response was not JSON\nBody:" . $this->_response->getBody(TRUE) . "\nError: " . $error );
		}
	}

	/**
	 * @Then /^the response is JSONP$/
	 */
	public function theResponseIsJsonp()
	{
		$result = preg_match('/^.+\(({.+})\)$/', $this->_response->getBody(TRUE), $matches);

		if ($result != 1 OR empty($matches[1]))
		{
			throw new Exception("Response was not JSONP\nBody:" . $this->_response->getBody(TRUE));
		}

		$data = json_decode($matches[1]);

		// Check for NULL not empty - since [] and {} will be empty but valid
		if ($data === NULL) {
			// Get further error info
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					$error = 'No errors';
				break;
				case JSON_ERROR_DEPTH:
					$error = 'Maximum stack depth exceeded';
				break;
				case JSON_ERROR_STATE_MISMATCH:
					$error = 'Underflow or the modes mismatch';
				break;
				case JSON_ERROR_CTRL_CHAR:
					$error = 'Unexpected control character found';
				break;
				case JSON_ERROR_SYNTAX:
					$error = 'Syntax error, malformed JSON';
				break;
				case JSON_ERROR_UTF8:
					$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
				default:
					$error = 'Unknown error';
				break;
			}
			
			throw new Exception("Response was not JSONP\nBody:" . $this->_response->getBody(TRUE) . "\nError: " . $error );
		}
	}

	/**
	 * @Given /^the response has a "([^"]*)" property$/
	 */
	public function theResponseHasAProperty($propertyName)
	{
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		$this->theResponseIsJson();

		if (Arr::path($data, $propertyName) === NULL) {
			throw new Exception("Property '".$propertyName."' is not set!\n");
		}
	}
	
	/**
	 * @Given /^the response does not have a "([^"]*)" property$/
	 */
	public function theResponseDoesNotHaveAProperty($propertyName)
	{
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		$this->theResponseIsJson();

		if (Arr::path($data, $propertyName) !== NULL) {
			throw new Exception("Property '".$propertyName."' is set but should not be!\n");
		}
	}

	/**
	 * @Then /^the "([^"]*)" property equals "([^"]*)"$/
	 */
	public function thePropertyEquals($propertyName, $propertyValue)
	{
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		$this->theResponseIsJson();

		$actualPropertyValue = Arr::path($data, $propertyName);

		if ($actualPropertyValue === NULL) {
			throw new Exception("Property '".$propertyName."' is not set!\n");
		}
		// Check the value - note this has to use != since $propertValue is always a string so strict comparison would fail.
		if ($actualPropertyValue != $propertyValue) {
			throw new \Exception('Property value mismatch on \''.$propertyName.'\'! (given: '.$propertyValue.', match: '.$actualPropertyValue.')');
		}
	}
	
	/**
	 * @Given /^the "([^"]*)" property contains "([^"]*)"$/
	 */
	public function thePropertyContains($propertyName, $propertyContainsValue)
	{
		
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		$this->theResponseIsJson();

		$actualPropertyValue = Arr::path($data, $propertyName);

		if ($actualPropertyValue === NULL) {
			throw new Exception("Property '".$propertyName."' is not set!\n");
		}
		
		if (is_array($actualPropertyValue) AND ! in_array($propertyContainsValue, $actualPropertyValue)) {
			throw new \Exception('Property \''.$propertyName.'\' does not contain value! (given: '.$propertyContainsValue.', match: '.json_encode($actualPropertyValue).')');
		}
		elseif (is_string($actualPropertyValue) AND strpos($actualPropertyValue, $propertyContainsValue) === FALSE)
		{
			throw new \Exception('Property \''.$propertyName.'\' does not contain value! (given: '.$propertyContainsValue.', match: '.$actualPropertyValue.')');
		}
		elseif (!is_array($actualPropertyValue) AND !is_string($actualPropertyValue))
		{
			throw new \Exception("Property '".$propertyName."' could not be compared. Must be string or array.\n");
		}
	}

	/**
	 * @Given /^the type of the "([^"]*)" property is "([^"]*)"$/
	 */
	public function theTypeOfThePropertyIs($propertyName, $typeString)
	{
		$data = json_decode($this->_response->getBody(TRUE), TRUE);

		$this->theResponseIsJson();

		$actualPropertyValue = Arr::path($data, $propertyName);

		if ($actualPropertyValue === NULL) {
			throw new Exception("Property '".$propertyName."' is not set!\n");
		}
		// check our type
		switch (strtolower($typeString)) {
			case 'numeric':
				if (!is_numeric($actualPropertyValue)) {
					throw new Exception("Property '".$propertyName."' is not of the correct type: ".$typeString."!\n");
				}
				break;
		}
	}

	/**
	 * @Then /^the response status code should be (\d+)$/
	 */
	public function theResponseStatusCodeShouldBe($httpStatus)
	{
		if ((string)$this->_response->getStatusCode() !== $httpStatus) {
			throw new \Exception('HTTP code does not match '.$httpStatus.
				' (actual: '.$this->_response->getStatusCode().')');
		}
	}

	 /**
	 * @Then /^echo last response$/
	 */
	public function echoLastResponse()
	{
		$this->printDebug(
			$this->_requestUrl."\n\n".
			$this->_response
		);
	}
}
