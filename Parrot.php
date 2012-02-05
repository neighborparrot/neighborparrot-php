<?
/*
 * NeighborParrot hosted broker service driver for php.
 */
class Parrot {
  
  private static $options = array('timeout' => 5);
  private static $SERVER_URL = "https://neighborparrot.net";
  
  /*
   * Configure default options, like api_id and api_key
   * @param [Array] options to configure. Valid options are:
   *   - api_id
   *   - api_key
   *   - timeout (defult 5)
   */
  public static function configure($opt){
    self::$options = array_merge(self::$options, $opt);
  }
  
  /*
   * Send a message to the given channel
   * @param [String] channel name
   * @param [String] message to send.
   * @return [String] Response from the server with error 
   * or message id if success.
   */
  public function send($channel, $message) {
    $signed_data = $this->sign_send_request($channel, $message);
    $url = self::$SERVER_URL.'/send';
    return $resp = $this->curl_post($url, $signed_data);
  }
  
  /*
   * Generate the json request needed for the javascript api.
   * @param [String] channel to connect
   * @service [String] 'es' for EventSource or 'ws' for WebSockets
   * @return [String] Json connect request valid for the js Parrot
   */
  public function generate_connect_request($channel, $service) {
    $request = $this->sign_connect_request($channel, $service);
    return json_encode($request);
  }
    
  /*
   * Check for required dependencies.
   * @fail if no compatible 
   */
  public static function test_compat(){
		if (!extension_loaded('curl') || !extension_loaded('json'))		{
			die ( 'There is missing dependant extensions - please ensure both cURL and JSON modules are installed' );
		}
    	 
    if (!in_array('sha256', hash_algos())) {
      die ( 'SHA256 appears to be unsupported - make sure you have support for it, or upgrade your version of PHP.' );
		}
  }
  
  /*
   * Send the post data to the server with curl
   * @param [String] url full url
   * @param [Array] data to send
   * @return [String] Response from the server with error 
   * or message id if success.
   */
  private function curl_post($url, $post_data){   
    $ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt( $ch, CURLOPT_TIMEOUT, self::$options['timeout'] );
    $response = curl_exec( $ch );    
		curl_close( $ch );
    return $response;
  }
  
  /*
   * Return signed data for send request
   * @param [String] desired channel
   * @param [String] message to send. Can be some kind of string serialization.
   * @return [Array] signed data with auth fields.
   */
  private function sign_send_request($channel, $message){
    $data = array("channel" => $channel, "data" => $message);    
    $type  =  "POST\n/send\n";
    return $this->sign_request($type, $data);
  }

  /*
   * Return signed data for connect request
   * @param [String] channel to connect
   * @service [String] 'es' for EventSource or 'ws' for WebSockets
   * @return [Array] signed data with auth fields.
   */
  private function sign_connect_request($channel, $service){
    $query = array("channel" => $channel);    
    $endpoint = $service == 'es' ? '/open' : '/ws';
    $type  =  "GET\n/".$endpoint."\n";
    return $this->sign_request($type, $query);    
  }

  /*
   * Add the required auth fields and sing the request.
   * @param [String] type of request ([GET|POST]\n[/open|/ws]
   * @param [Array] data to sign
   * @return [Array] signed data with auth fields.
   */
  private function sign_request($type, $data) {
    $time = time();
    $auth = array("auth_key" => self::$options['api_id'], 
                  "auth_timestamp" => $time,
                  "auth_version" => "1.0");

    $signed_data = array_merge($data, $auth);
    $str_to_sign = $type.$this->array_to_query($signed_data);
    $signature = hash_hmac( 'sha256', $str_to_sign, self::$options['api_key'], false );
    return array_merge($signed_data, array('auth_signature' =>$signature));
  }

  /*
   * Generate a valid url query from an array
   * Used when sign the requests.
   */
  private function array_to_query($params) {
    ksort($params);    
    $func = function($key, $value) {
      return $key."=".$value;
    };    
    $values = array_map($func, array_keys($params), array_values($params));
    return implode("&", $values);
  }
}
?>