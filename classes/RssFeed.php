<?php

/**
 * RSS Parser to retrieve items..
 *
 */

require_once('iSocialFeed.php');
require_once('Base.php');

class RssFeed extends Base implements iSocialFeed {

	public function __construct() {

	}

	public function getFeed ($options) {

		if (empty($options['sa_rss_url'])) return array('error' => 4, 'message' => 'Error fetching RSS feed: <span class="social-feed-error">No URL provided.</span>');

		$since_time = empty($options['sa_since_time']) ? 1 : $options['sa_since_time'];

		try {
			$rss = Feed::loadRss($options['sa_rss_url']);
		}
		catch (FeedException $e) {
			return array('error' => 5, 'message' => 'Error fetching RSS feed: <span class="social-feed-error">' . $e->getMessage() . '</span>');
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

				$author = (string)$item->author;

				if (empty($author)) {
					$namespaces = $item->getNameSpaces(true);
					$dc = $item->children($namespaces['dc']);
					$p['author'] = (string)$dc->creator;
				}

				$p['date_added'] = $date_added;
				$p['date_created'] = strtotime((string)$item->pubDate);

				$item_timestamps[] = $item_timestamp;
				array_push($data, $p);
			}

		}

		if (count($data) > 0) {
			$res = array('data' => $data, 'since_time' => max($item_timestamps));
			return $res;
		}
		else {
			return array('error' => 3, 'message' => 'No new RSS items found.');
		}
	}
}
