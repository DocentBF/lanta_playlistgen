<?php

/**
 * Class PlaylistGenerator
 * Порядок запросов
http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/profiles?mac=макадрес
// ответ json - [{"id":11734,"isRoot":true,"name":"root"}]
http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/profiles/set_profile?profile_id=ид_из_предыдущего&mac=макадрес
// ответ true
http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/channels
// ответ json - список каналов
 */
class PlaylistGenerator {
	const USER_AGENT = "Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 4 rev: 2738 Mobile Safari/533.3";

	protected $mac;
	protected $profileId;
	protected $channelsData;
	protected $proxy;

	public function __construct($mac) {
		$this->mac = $mac;
		$this->proxy = isset($_REQUEST['proxy']) ? $_REQUEST['proxy'] : false;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function generate() {
		$this->fetch();
		return $this->buildPlaylist();
	}

	/**
	 * @throws Exception
	 */
	protected function fetch() {
		$this->firstAuth();
		$this->secondAuth();
		$this->setChannelsData();
	}

	/**
	 * @throws Exception
	 */
	protected function firstAuth() {
		$url = "http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/profiles?mac={$this->mac}";
		$result = $this->request($url);
		$result = $this->parseJson($result);
		$this->profileId = $result[0]->id;
	}

	/**
	 * @throws Exception
	 */
	protected function secondAuth() {
		$url = "http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/profiles/set_profile?profile_id={$this->profileId}&mac={$this->mac}";
		$result = $this->request($url);
		if (trim($result) != "true") {
			throw new Exception("Second auth error");
		}

	}

	/**
	 * @throws Exception
	 */
	protected function setChannelsData() {
		$url = "http://stb.lanta-net.ru/stb-client-4/seam/resource/rest/channels";
		$result = $this->request($url);
		$this->channelsData = $this->parseJson($result);
		if (!isset($this->channelsData->all)) {
			throw new Exception("Can't get list channels. 3rd step");
		}

	}

	/**
	 * @param $url
	 * @return bool|string
	 * @throws Exception
	 */
	protected function request($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$cookie = dirname(__FILE__) . '/cookie/' . md5(parse_url($url, PHP_URL_HOST)) . '.txt';
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		$response = curl_exec($ch);
		if (curl_errno($ch) > 0) {
			throw new Exception("Curl error: " . curl_error($ch));
		}
		curl_close($ch);
		return $response;
	}

	/**
	 * @param $response
	 * @return mixed
	 * @throws Exception
	 */
	protected function parseJson($response) {
		$response = json_decode($response);
		if (json_last_error() != JSON_ERROR_NONE) {
			throw new Exception("Result is not JSON " . print_r($response, 1));
		}

		return $response;
	}

	/**
	 * @param $proto
	 * @param $addr
	 * @param null $port
	 * @return string
	 */
	protected function channelUrl($proto, $addr, $port = null) {
		$port = is_null($port) ? "" : ":{$port}";
		if ($this->proxy !== false) {
			$url = "http://{$this->proxy}/{$proto}/{$addr}{$port}";
		} else {
			$proto = in_array($proto, ['udp', 'rtp']) ? "{$proto}://@" : "$proto://";
			$url = "{$proto}{$addr}{$port}";
		}
		return $url;
	}

	/**
	 * @return string
	 */
	protected function buildPlaylist() {
		$playlistRows = [];
		$playlistRows[] = '#EXTM3U url-tvg="http://tv.lanta-net.ru/tvprogram.zip" tvg-shift="+3"';
		foreach ($this->channelsData->all as $channel) {
			if (!$channel->available) {
				continue;
			}

			$archive = "";
			if (isset($channel->dvrStreamName)) {
				$archive = 'catchup="default" catchup-source="http://193.203.61.11/' . $channel->dvrStreamName . '/index-${start}-${offset}.m3u8" catchup-days=7 ';
			}
			$playlistRows[] = '#EXTINF:-1 tvg-name="' . $channel->id . '" group_id="' . $channel->category_id . '" ' . $archive .
			'lanta_tv_id="' . $channel->id . '" tvg-logo="http://tv.lanta-net.ru/' . $channel->id . '.png",' . $channel->name;
			$playlistRows[] = $this->channelUrl($channel->mod, $channel->address, $channel->port);
		}
		return implode("\n", $playlistRows);
	}

}
