<?php

namespace App\Tests;

use App\DataFixtures\MongoDB\AgencyFixtures;
use App\DataFixtures\MongoDB\ContentFixtures;
use App\Rest\RestContentRequest;

/**
 * Class ContentFetchTest
 *
 * Functional tests for fetching content related entries.
 */
class ContentFetchTest extends AbstractFixtureAwareTest
{
    use AssertResponseStructureTrait;

    const URI = '/content/fetch';

    /**
     * Fetch with wrong key.
     */
    public function testFetchWithWrongKey()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY.'-wrong',
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertFalse($result['status']);
        $this->assertEmpty($result['items']);
        $this->assertEquals($result['message'], 'Failed validating request. Check your credentials (agency & key).');
        $this->assertArrayHasKey('hits', $result);
        $this->assertEquals(0, $result['hits']);
    }

    /**
     * Fetch with missing data.
     */
    public function testFetchWithEmptyAgency()
    {
        $parameters = [
            'agency' => '',
            'key' => self::KEY,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertFalse($result['status']);
        $this->assertEmpty($result['items']);
        $this->assertEquals($result['message'], 'Failed validating request. Check your credentials (agency & key).');
        $this->assertArrayHasKey('hits', $result);
        $this->assertEquals(0, $result['hits']);
    }

    /**
     * Fetch by nid.
     */
    public function testFetchByNid()
    {
        $nid = 1000;
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'node' => $nid,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals($nid, $result['items'][0]['nid']);
        $this->assertEquals(self::AGENCY, $result['items'][0]['agency']);
        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetch by multiple nid's.
     *
     * TODO: Implement also test fetch by id.
     */
    public function testFetchByMultipleNid()
    {
        $nids = array_merge(
            range(mt_rand(1000, 1005), mt_rand(1006, 1010)),    // os nodes
            range(mt_rand(2000, 2005), mt_rand(2006, 2010))     // editorial nodes
        );
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'node' => implode(',', $nids),
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertContains($item['nid'], $nids);
            $this->assertEquals(self::AGENCY, $item['agency']);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetch by type.
     */
    public function testFetchByType()
    {
        $type = 'editorial';
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'type' => $type,
            'external' => -1,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        foreach ($result['items'] as $item) {
            $this->assertEquals($type, $item['type']);
            $this->assertEquals(self::AGENCY, $item['agency']);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Default fetch.
     */
    public function testFetchAll()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        // 10 items are returned by default.
        $this->assertCount(10, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertEquals(self::AGENCY, $item['agency']);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Limited fetch.
     */
    public function testFetchWithSmallAmount()
    {
        $amount = 2;
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'amount' => $amount,
            'type' => 'os',
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $this->assertCount($amount, $result['items']);
        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Paged fetch.
     */
    public function testFetchWithPager()
    {
        $skip = 0;
        $amount = 2;
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'amount' => $amount,
            'skip' => $skip,
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        $node_ids = [];
        // Fetch items till empty result set.
        while (true) {
            $response = $this->request(self::URI, $parameters, 'GET');

            $result = $this->assertResponse($response);

            if (empty($result['items'])) {
                break;
            }

            $this->assertLessThanOrEqual($amount, count($result['items']));
            $this->assertGreaterThan(0, $result['hits']);

            foreach ($result['items'] as $item) {
                // Node id's normally should not repeat for same agency.
                $this->assertNotContains($item['nid'], $node_ids);
                $this->assertEquals(self::AGENCY, $item['agency']);
                $node_ids[] = $item['nid'];
            }

            $skip += $amount;
            $parameters['skip'] = $skip;
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertCount($result['hits'], $node_ids);
        // Expect zero, since we reached end of the list.
        $this->assertCount(0, $result['items']);
    }

    /**
     * Fetch sorted.
     */
    public function testFetchWithSorting()
    {
        $sort = 'nid';
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'sort' => $sort,
            'order' => 'asc',
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        // Ascending sort.
        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        for ($i = 1; $i < count($result['items']); $i++) {
            $this->assertGreaterThan($result['items'][$i - 1][$sort], $result['items'][$i][$sort]);
        }

        // Descending sort.
        $parameters['order'] = 'desc';

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        for ($i = 1; $i < count($result['items']); $i++) {
            $this->assertLessThan($result['items'][$i - 1][$sort], $result['items'][$i][$sort]);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetch sorted by complex field.
     */
    public function testFetchWithNestedFieldSorting()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'sort' => 'fields.title.value',
            'order' => 'asc',
            'type' => 'os',
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        // Ascending order.
        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        for ($i = 1; $i < count($result['items']); $i++) {
            $first_node = $result['items'][$i];
            $second_node = $result['items'][$i - 1];
            $comparison = strcmp($first_node['fields']['title']['value'], $second_node['fields']['title']['value']);
            $this->assertGreaterThan(0, $comparison);
        }

        // Descending order;
        $parameters['order'] = 'desc';

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        for ($i = 1; $i < count($result['items']); $i++) {
            $first_node = $result['items'][$i];
            $second_node = $result['items'][$i - 1];
            $comparison = strcmp($first_node['fields']['title']['value'], $second_node['fields']['title']['value']);
            $this->assertLessThan(0, $comparison);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetch by complex filtering.
     */
    public function testFetchComplex()
    {
        $type = 'os';
        $amount = 2;
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'type' => $type,
            'amount' => $amount,
            'skip' => 1,
            'sort' => 'fields.title.value',
            'order' => 'desc',
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_ALL,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertCount($amount, $result['items']);

        // Check some static values.
        foreach ($result['items'] as $item) {
            $this->assertEquals(self::AGENCY, $item['agency']);
            $this->assertEquals($type, $item['type']);
        }

        // Check order.
        for ($i = 1; $i < count($result['items']); $i++) {
            $first_node = $result['items'][$i];
            $second_node = $result['items'][$i - 1];
            $comparison = strcmp($first_node['fields']['title']['value'], $second_node['fields']['title']['value']);
            $this->assertLessThan(0, $comparison);
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetches default set of published content.
     */
    public function testFetchByDefaultStatus()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'type' => 'os',
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        foreach ($result['items'] as $item) {
            $status = $item['fields']['status']['value'];
            $this->assertContains(
                $status,
                [
                    RestContentRequest::STATUS_PUBLISHED,
                    RestContentRequest::STATUS_UNPUBLISHED,
                ]
            );
        }

        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetches content filtered by status.
     */
    public function testFetchByStatus()
    {
        // Fetch published content.
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'status' => RestContentRequest::STATUS_PUBLISHED,
            'type' => 'os',
            'amount' => 10,
            'skip' => 0,
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        $publishedCount = count($result['items']);

        foreach ($result['items'] as $item) {
            $status = $item['fields']['status']['value'];
            $this->assertEquals($parameters['status'], $status);
        }

        // Fetch unpublished content.
        $parameters['status'] = RestContentRequest::STATUS_UNPUBLISHED;

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);

        $unpublishedCount = count($result['items']);

        foreach ($result['items'] as $item) {
            $status = $item['fields']['status']['value'];
            $this->assertEquals($parameters['status'], $status);
        }

        // Fetch all content.
        $parameters['status'] = RestContentRequest::STATUS_ALL;
        $parameters['amount'] = 999;

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $this->assertCount($publishedCount + $unpublishedCount, $result['items']);
        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['hits']);
    }

    /**
     * Fetches content filtered by external status.
     */
    public function testFetchByExternal()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'status' => RestContentRequest::STATUS_ALL,
            'external' => RestContentRequest::STATUS_PUBLISHED,
            'type' => 'os',
            'amount' => 999,
            'skip' => 0,
        ];

        // Fetch external movies.
        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $externalCount = count($result['items']);
        $this->assertEquals($result['hits'], $externalCount);

        // Fetch non-external movies.
        $parameters['external'] = RestContentRequest::STATUS_UNPUBLISHED;

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $nonExternalCount = count($result['items']);
        $this->assertEquals($result['hits'], $externalCount);

        // Fetch all movies.
        $parameters['external'] = RestContentRequest::STATUS_ALL;

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $allCount = count($result['items']);
        $this->assertEquals($result['hits'], $allCount);

        $this->assertEquals($allCount, $externalCount + $nonExternalCount);
    }

    /**
     * Fetches items and sorts in an exact manner.
     */
    public function testExactOrder()
    {
        $parameters = [
            'agency' => self::AGENCY,
            'key' => self::KEY,
            'node' => implode(',', [1000,1001,2000,2001]),
            'amount' => 999,
            'skip' => 0,
            'sort' => 'nid',
            'order' => 'match(2001,1001)',
        ];

        $response = $this->request(self::URI, $parameters, 'GET');

        $result = $this->assertResponse($response);

        $this->assertNotEmpty($result['items']);
        $allCount = count($result['items']);
        $this->assertEquals($result['hits'], $allCount);

        // Expect same order as in order=match() query parameter.
        $this->assertEquals(2001, $result['items'][0]['nid']);
        $this->assertEquals(1001, $result['items'][1]['nid']);
    }

    /**
     * {@inheritdoc}
     */
    public function getFixtures()
    {
        return [
            new AgencyFixtures(),
            new ContentFixtures(),
        ];
    }
}
