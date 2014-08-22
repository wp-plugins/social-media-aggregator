<?php

/**
 * Facebook class to retrieve news feed..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

class FacebookFeed extends Base implements iSocialFeed {

	public function __construct() {

	}

	public function getFeed ($options) {

		if (empty($options['sa_fb_app_id']) && empty($options['sa_fb_app_secret'])) return array('error' => 2, 'message' => 'Facebook App ID and/or secret not set. Please set this in the settings page.');

		$facebook = new Facebook (array(
		  'appId'  => $options['sa_fb_app_id'],
		  'secret' => $options['sa_fb_app_secret']
		));

		if (empty($options['sa_fb_page_id'])) return array('error' => 2, 'message' => 'Facebook Page ID not set.');

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		try {
			// $user_profile = $facebook->api('/me');
			// $this->log($user_profile);
			$response = $facebook->api($options['sa_fb_page_id'] . '/feed/?fields=id,type,caption,picture,message,link,object_id&limit=25&since=' . $since_time);
		} catch (FacebookApiException $e) {
			// $loginUrl = $facebook->getLoginUrl(array('scope' => 'publish_stream', 'redirect_uri' => 'http://freefly.ryan.invokedev.com/'));
			$result = $e->getResult();
			return array('error' => 4, 'message' => 'Error fetching Facebook feed: <span class="social-feed-error">' . $result['error']['message'] . '</span>');
			// $loginUrl = $facebook->getLoginUrl();
			// $user = null;
			// echo "<script>top.location.href = '$loginUrl'</script>";
			// exit;
		}

		// return an array with success = false if $response is empty..
		if (count($response['data']) == 0) return array('error' => 1, 'message' => 'No Facebook items found.');

		$data = array();

		$date_added = time();

		$created_times = array(0);

		foreach ($response['data'] as $post) {
			if ($post['type'] == 'photo') {

				// get images..
				$images = $facebook->api($post['object_id'] . '?fields=images');

				$p = array();

				foreach ($images['images'] as $item) {
					if ($item['height'] >= 480) {
						$p['picture'] = $item['source'];
						break;
					}
				}

				$p['id'] = $post['id'];
				$p['message'] = (empty($post['message'])) ? '' : $post['message'];
				$p['link'] = (empty($post['link'])) ? '' : $post['link'];
				$p['date_added'] = $date_added;

				$created_time = new DateTime($post['created_time']);
				$p['date_created'] = $created_time->format('U');
				$created_times[] = $created_time->format('U');

				array_push($data, $p);
			}
		}

		if (count($data) == 0) return array('error' => 3, 'message' => 'No new Facebook items found.');

		$res = array('data' => $data, 'since_time' => max($created_times));

		return $res;

	}
}
