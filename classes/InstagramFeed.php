<?php

/**
 * Instagram class to retrieve posts..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

use Instagram\Instagram;
use Instagram\Core\ApiException;

class InstagramFeed extends Base implements iSocialFeed {

	public function __construct() {

	}

	public function getFeed ($options) {

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		// $auth_config = array(
		// 	'client_id' => $options['sa_insta_client_id'],
		// 	'client_secret' => $options['sa_insta_client_secret'],
		// 	'redirect_uri' => $options['sa_insta_callback'],
		// 	'scope' => array('basic')
		// );

		// $auth = new Instagram\Auth($auth_config);

		// if (!empty($_GET['code'])) {
		// 	$_SESSION['instagram_access_token'] = $auth->getAccessToken($_GET['code']);
		// }
		// else {
		// 	$auth->authorize();
		// }

		// $this->log($_SESSION['instagram_access_token']);

		$instagram = new Instagram;
		$instagram->setAccessToken($options['sa_insta_access_token']);


		try {
			// $user = $instagram->getUser('343124091');
			$user = $instagram->getUserByUsername($options['sa_insta_screenname']);
			// $user = $instagram->getTag('freefly'); // if wanting to use tags..
			$media = $user->getMedia(array('min_timestamp' => $since_time));
		}
		catch (ApiException $e) {
			$this->log ($e);
			return array('error' => 4, 'message' => 'Error fetching Instagram feed: <span class="social-feed-error">' . $e->getMessage() . '</span>');
		}

		// return an array with success = false if $response is empty..
		if (count($media) == 0) return array('error' => 1, 'message' => 'No Instagram items found.');

		$data = array();
		$date_added = time();
		$since_ids = array();

		foreach ($media as $photo) {

			$p = array();
			$p['id'] = $photo->getId();
			$p['author'] = $photo->getUser()->getUserName();

			if ($photo->getCaption()) $p['message'] = $photo->getCaption()->text;

			$p['picture'] = $photo->getStandardResImage()->url;
			$p['link'] = $photo->getLink();
			$p['date_added'] = $date_added;
			$p['date_created'] = $photo->getCreatedTime(); // already a unix timestamp..

			$since_ids[] = $photo->getCreatedTime();
			array_push($data, $p);
		}

		if (count($data) == 0) return array('error' => 3, 'message' => 'No new Instagram items found.');

		$res = array('data' => $data, 'since_time' => max($since_ids) + 1); // someone somewhere must be using => instead of >, so I'm adding 1 here to not include the equality..
		return $res;

	}
}
