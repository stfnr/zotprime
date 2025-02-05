<?
/*
    ***** BEGIN LICENSE BLOCK *****
    
    This file is part of the Zotero Data Server.
    
    Copyright © 2012 Center for History and New Media
                     George Mason University, Fairfax, Virginia, USA
                     http://zotero.org
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.
    
    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***** END LICENSE BLOCK *****
*/

namespace APIv3;
use API3 as API, SimpleXMLElement;
require_once 'APITests.inc.php';
require_once 'include/api3.inc.php';

class GroupTests extends APITests {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require 'include/config.inc.php';
		API::userClear($config['userID']);
	}
	
	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		require 'include/config.inc.php';
		API::userClear($config['userID']);
	}
	
	
	/**
	 * Changing a group's metadata should change its version
	 */
	public function testUpdateMetadataJSON() {
		$response = API::userGet(
			self::$config['userID'],
			"groups"
		);
		$this->assert200($response);
		
		// Get group API URI and version
		$json = API::getJSONFromResponse($response)[0];
		$groupID = $json['id'];
		$url = $json['links']['self']['href'];
		$url = str_replace(self::$config['apiURLPrefix'], '', $url);
		$version = $json['version'];
		
		// Make sure format=versions returns the same version
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$this->assertEquals($version, json_decode($response->getBody())->$groupID);
		
		// Update group metadata
		$xml = new SimpleXMLElement("<group/>");
		foreach ($json['data'] as $key => $val) {
			switch ($key) {
			case 'id':
			case 'version':
			case 'members':
				continue;
			
			case 'name':
				$name = "My Test Group " . uniqid();
				$xml['name'] = $name;
				break;
			
			case 'description':
				$description = "This is a test description " . uniqid();
				$xml->$key = $description;
				break;
			
			case 'url':
				$urlField = "http://example.com/" . uniqid();
				$xml->$key = $urlField;
				break;
			
			default:
				$xml[$key] = $val;
			}
		}
		$xml = trim(preg_replace('/^<\?xml.+\n/', "", $xml->asXML()));
		
		$response = API::put(
			$url,
			$xml,
			array("Content-Type: text/xml"),
			array(
				"username" => self::$config['rootUsername'],
				"password" => self::$config['rootPassword']
			)
		);
		$this->assert200($response);
		$xml = API::getXMLFromResponse($response);
		$xml->registerXPathNamespace('zxfer', 'http://zotero.org/ns/transfer');
		$group = $xml->xpath('//atom:entry/atom:content/zxfer:group');
		$this->assertCount(1, $group);
		$this->assertEquals($name, $group[0]['name']);
		
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$json = json_decode($response->getBody());
		$newVersion = $json->$groupID;
		$this->assertNotEquals($version, $newVersion);
		
		// Check version header on individual group request
		$response = API::groupGet(
			$groupID,
			""
		);
		$this->assert200($response);
		$this->assertEquals($newVersion, $response->getHeader('Last-Modified-Version'));
		$json = API::getJSONFromResponse($response)['data'];
		$this->assertEquals($name, $json['name']);
		$this->assertEquals($description, $json['description']);
		$this->assertEquals($urlField, $json['url']);
	}
	
	
	/**
	 * Changing a group's metadata should change its version
	 */
	public function testUpdateMetadataAtom() {
		$response = API::userGet(
			self::$config['userID'],
			"groups?content=json&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		
		// Get group API URI and version
		$xml = API::getXMLFromResponse($response);
		$xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
		$xml->registerXPathNamespace('zapi', 'http://zotero.org/ns/api');
		$groupID = (string) array_get_first($xml->xpath("//atom:entry/zapi:groupID"));
		$url = (string) array_get_first($xml->xpath("//atom:entry/atom:link[@rel='self']/@href"));
		$url = str_replace(self::$config['apiURLPrefix'], '', $url);
		$version = json_decode(API::parseDataFromAtomEntry($xml)['content'], true)['version'];
		
		// Make sure format=versions returns the same version
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$json = json_decode($response->getBody());
		$this->assertEquals($version, $json->$groupID);
		
		// Update group metadata
		$json = json_decode(array_get_first($xml->xpath("//atom:entry/atom:content")));
		$xml = new SimpleXMLElement("<group/>");
		foreach ($json as $key => $val) {
			switch ($key) {
			case 'id':
			case 'members':
				continue;
			
			case 'name':
				$name = "My Test Group " . uniqid();
				$xml['name'] = $name;
				break;
			
			case 'description':
				$description = "This is a test description " . uniqid();
				$xml->$key = $description;
				break;
			
			case 'url':
				$urlField = "http://example.com/" . uniqid();
				$xml->$key = $urlField;
				break;
			
			default:
				$xml[$key] = $val;
			}
		}
		$xml = trim(preg_replace('/^<\?xml.+\n/', "", $xml->asXML()));
		
		$response = API::put(
			$url,
			$xml,
			array("Content-Type: text/xml"),
			array(
				"username" => self::$config['rootUsername'],
				"password" => self::$config['rootPassword']
			)
		);
		$this->assert200($response);
		$xml = API::getXMLFromResponse($response);
		$xml->registerXPathNamespace('zxfer', 'http://zotero.org/ns/transfer');
		$group = $xml->xpath('//atom:entry/atom:content/zxfer:group');
		$this->assertCount(1, $group);
		$this->assertEquals($name, $group[0]['name']);
		
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$json = json_decode($response->getBody());
		$newVersion = $json->$groupID;
		$this->assertNotEquals($version, $newVersion);
		
		// Check version header on individual group request
		$response = API::groupGet(
			$groupID,
			"?content=json&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$this->assertEquals($newVersion, $response->getHeader('Last-Modified-Version'));
		$json = json_decode(API::getContentFromResponse($response));
		$this->assertEquals($name, $json->name);
		$this->assertEquals($description, $json->description);
		$this->assertEquals($urlField, $json->url);
	}
	
	
	public function testUpdateMemberJSON() {
		$groupID = API::createGroup([
			'owner' => self::$config['userID'],
			'type' => 'Private',
			'libraryReading' => 'all'
		]);
		
		// Get group version
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$version = json_decode($response->getBody())->$groupID;
		
		$response = API::superPost(
			"groups/$groupID/users",
			'<user id="' . self::$config['userID2'] . '" role="member"/>',
			["Content-Type: text/xml"]
		);
		$this->assert200($response);
		
		// Group metadata version should have changed
		$response = API::userGet(
			self::$config['userID'],
			"groups?format=versions&key=" . self::$config['apiKey']
		);
		$this->assert200($response);
		$json = json_decode($response->getBody());
		$newVersion = $json->$groupID;
		$this->assertNotEquals($version, $newVersion);
		
		// Check version header on individual group request
		$response = API::groupGet($groupID, "");
		$this->assert200($response);
		$this->assertEquals($newVersion, $response->getHeader('Last-Modified-Version'));
		
		API::deleteGroup($groupID);
	}
	
	
	public function test_group_should_not_appear_in_search_until_first_populated() {
		$name = \Zotero_Utilities::randomString(14);
		$groupID = API::createGroup([
			'owner' => self::$config['userID'],
			'type' => 'PublicClosed',
			'name' => $name,
			'libraryReading' => 'all'
		]);
		
		// Group shouldn't show up if it's never had items
		$response = API::superGet("groups?q=$name");
		$this->assertNumResults(0, $response);
		
		API::groupCreateItem($groupID, "book", false, $this);
		
		$response = API::superGet("groups?q=$name");
		$this->assertNumResults(1, $response);
		
		API::deleteGroup($groupID);
	}
	
	
	public function testDeleteGroup() {
		$groupID = API::createGroup([
			'owner' => self::$config['userID'],
			'type' => 'Private',
			'libraryReading' => 'all'
		]);
		API::groupCreateItem($groupID, "book", false, $this, 'key');
		API::groupCreateItem($groupID, "book", false, $this, 'key');
		API::groupCreateItem($groupID, "book", false, $this, 'key');
		API::deleteGroup($groupID);
		
		$response = API::groupGet($groupID, "");
		$this->assert404($response);
	}
}
