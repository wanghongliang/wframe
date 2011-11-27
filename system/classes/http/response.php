<?php defined('PATH_SYS') or die('不能直接访问.'); 
interface HTTP_Response extends HTTP_Message {

	/**
	 * Sets or gets the HTTP status from this response.
	 *
	 *      // Set the HTTP status to 404 Not Found
	 *      $response = Response::factory()
	 *              ->status(404);
	 *
	 *      // Get the current status
	 *      $status = $response->status();
	 *
	 * @param   integer  $code  Status to set to this response
	 * @return  mixed
	 */
	public function status($code = NULL);

}