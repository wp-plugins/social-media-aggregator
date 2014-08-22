<?php

/**
 * YouTubeFeed
 * Uses RssFeed to parse..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

class VimeoFeed extends Base implements iSocialFeed {

	public function __construct() {
	}

	public function getFeed ($options) {

		if (empty($options['sa_vim_username'])) return array('error' => 4, 'message' => 'Error fetching Vimeo feed: <span class="social-feed-error">No user found.</span>');

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		try {
			$rss = Feed::loadRss('http://vimeo.com/' . $options['sa_vim_username'] . '/videos/rss');
		}
		catch (FeedException $e) {
			return array('error' => 5, 'message' => 'Error fetching Vimeo feed: <span class="social-feed-error">' . $e->getMessage() . '</span>');
		}


		if (count($rss->item) == 0) return array('error' => 1, 'message' => 'No items found in feed.');

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

				$namespaces = $item->getNameSpaces(true);
				$dc = $item->children($namespaces['dc']);
				$p['author'] = (string)$dc->creator;

				$p['date_added'] = $date_added;
				$p['date_created'] = strtotime((string)$item->pubDate);

				$video_id = substr($link, strrpos($link, '/') + 1);
				$p['video_id'] = $video_id;

				$vimeo_data = file_get_contents('http://vimeo.com/api/v2/video/' . $video_id .'.json');
				$vimeo_data = json_decode($vimeo_data);

				$vimeo_thumb = $vimeo_data[0]->thumbnail_large;

				$p['picture'] = $vimeo_thumb;

				$item_timestamps[] = $item_timestamp;
				array_push($data, $p);
			}

		}

		if (count($data) > 0) {
			$res = array('data' => $data, 'since_time' => max($item_timestamps));
			return $res;
		}
		else {
			return array('error' => 3, 'message' => 'No new Vimeo items found.');
		}
	}

}
