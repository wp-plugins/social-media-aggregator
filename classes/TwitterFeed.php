<?php

/**
 * Twitter class to retrieve tweets..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

use TwitterOAuth\TwitterOAuth;
use TwitterOAuth\Exception\TwitterException;

class TwitterFeed extends Base implements iSocialFeed {

	public function __construct() {

	}

	public function getFeed ($options) {

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		$config = array(
			'consumer_key' => $options['sa_tw_key'],
			'consumer_secret' => $options['sa_tw_secret'],
			'oauth_token' => $options['sa_tw_token'],
			'oauth_token_secret' => $options['sa_tw_token_secret'],
			'output_format' => 'object'
		);

		$tw = new TwitterOAuth($config);

		/**
		 * Returns a collection of the most recent Tweets posted by the user
		 * https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		 */
		$params = array(
			'screen_name' => $options['sa_tw_screenname'],
			'count' => 10,
			'since_id' => $since_time,
			'exclude_replies' => true
		);

		// $params = array(
		// 	'q' => '#freefly',
		// 	'count' => 20,
		// 	'since_id' => $since_id
		// );

		/**
		 * Send a GET call with set parameters
		 */
		try {
			$response = $tw->get('statuses/user_timeline', $params);
		}
		catch (TwitterException $e) {
			return array('error' => 4, 'message' => 'Error fetching Twitter feed: <span class="social-feed-error">' . $e->getMessage() . '</span>');
		}

		// return an array with success = false if $response is empty..
		if (count($response) == 0) return array('error' => 1, 'message' => 'No Twitter items found.');

		$data = array();
		$date_added = time();
		$since_ids = array();

		foreach ($response as $post) {

			$p = array();
			$p['id'] = $post->id;
			$p['message'] = $post->text;
			$p['link'] = 'https://www.twitter.com/' . $post->user->screen_name;
			$p['date_added'] = $date_added;
			$p['date_created'] = strtotime($post->created_at);

			$since_ids[] = $post->id;
			array_push($data, $p);
		}

		if (count($data) == 0) return array('error' => 3, 'message' => 'No new Twitter items found.');

		$res = array('data' => $data, 'since_time' => max($since_ids));

		return $res;
	}
}
