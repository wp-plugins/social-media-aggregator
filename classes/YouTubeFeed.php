<?php

/**
 * YouTubeFeed
 * Uses RssFeed to parse..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

class YouTubeFeed extends Base implements iSocialFeed {

	public function __construct() {
	}

	public function getFeed ($options) {

		if (empty($options['sa_yt_username'])) return array('error' => 4, 'message' => 'Error fetching YouTube feed: <span class="social-feed-error">No user found.</span>');

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		$rss = Feed::loadRss('http://youtube.com/rss/user/' . $options['sa_yt_username']);

		if (count($rss->item) == 0) return array('error' => 6, 'message' => 'Error fetching YouTube feed: <span class="social-feed-error">No items found or user does not exist.</span>');

		$data = array();
		$date_added = time();
		$item_timestamps = array();

		foreach ($rss->item as $item) {

			// $item_timestamp = (int)$item->timestamp;
			$item_timestamp = strtotime((string)$item->pubDate);

			if ($item_timestamp > $since_time) {
				$p = array();
				$p['id'] = (string)$item->guid;
				$p['message'] = (string)$item->title;
				$p['description'] = (string)$item->description;

				$link = (string)$item->link;
				$p['link'] = $link;

				$p['author'] = (string)$item->author;
				$p['date_added'] = $date_added;
				$p['date_created'] = strtotime((string)$item->pubDate);

				$video_id = substr($link, strrpos($link, 'watch?v=') + 8);
				$video_id = substr($video_id, 0, strpos($video_id, '&'));
				$p['video_id'] = $video_id;

				$p['picture'] = 'http://img.youtube.com/vi/' . $video_id . '/0.jpg';

				$item_timestamps[] = $item_timestamp;
				array_push($data, $p);
			}

		}

		if (count($data) > 0) {
			$res = array('data' => $data, 'since_time' => max($item_timestamps));
			return $res;
		}
		else {
			return array('error' => 3, 'message' => 'No new YouTube items found.');
		}
	}

}
