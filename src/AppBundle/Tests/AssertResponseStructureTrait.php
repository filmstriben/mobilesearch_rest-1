<?php

namespace AppBundle\Tests;

/**
 * Trait AssertResponseStructureTrait
 *
 * Wraps the API response structure assertions.
 */
trait AssertResponseStructureTrait
{
    /**
     * Asserts data structure of a response.
     *
     * @param array $response Response array.
     */
    public function assertResponseStructure(array $response)
    {
        $this->assertArrayHasKey('status', $response);
        $this->assertInternalType('boolean', $response['status']);
        $this->assertArrayHasKey('message', $response);
        $this->assertInternalType('string', $response['message']);
        $this->assertArrayHasKey('items', $response);
        $this->assertInternalType('array', $response['items']);
    }
}
