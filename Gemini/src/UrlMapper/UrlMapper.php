<?php

namespace Islandora\Gemini\UrlMapper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use \Monolog\Logger;

/**
 * Class UrlMapper
 * @package Islandora\Crayfish\Commons
 */
class UrlMapper implements UrlMapperInterface
{

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Monolog\Logger
     */
    protected $log;

    /**
     * UrlMapper constructor.
     * @param \Doctrine\DBAL\Connection $connection
     */
    public function __construct(Connection $connection, Logger $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function getUrls($uuid)
    {
        $sql = 'SELECT drupal_uri as drupal, fedora_uri as fedora FROM Gemini WHERE uuid = :uuid';
        return $this->connection->fetchAssoc(
            $sql,
            ['uuid' => $uuid]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function saveUrls(
        $uuid,
        $drupal_uri,
        $fedora_uri
    ) {
        // Hash incomming URIs
        $fedora_hash = hash('sha512', $fedora_uri);
        $drupal_hash = hash('sha512', $drupal_uri);
        $now = date("Y-m-d H:i:s", time());
        $db_data = [
          'uuid' => $uuid,
          'drupal_uri' => $drupal_uri,
          'fedora_uri' => $fedora_uri,
          'drupal_hash' => $drupal_hash,
          'fedora_hash' => $fedora_hash,
          'dateCreated' => $now,
          'dateUpdated' => $now,
        ];

        $this->logger->debug("Mapping " . $db_data['drupal_uri'] . ' to ' . $db_data['fedora_uri'] . ' for ' . $db_data['uuid']);

        // Try to insert first, and if the record already exists, update it.
        try {
            $this->connection->insert('Gemini', $db_data);
            $this->logger->debug("SUCCESSFULLY CREATED ROW"); 
            return true;
        } catch (UniqueConstraintViolationException $e) {
            // We want to maintain the creation UNIX Timestamp
            unset($db_data['dateCreated']);
            unset($db_data['uuid']);
            $updated = $this->connection->update('Gemini', $db_data, ['uuid' => $uuid]);
            $this->logger->debug("GOT AN UPDATE: $updated rows affected"); 
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteUrls($uuid)
    {
        $count = $this->connection->delete(
            'Gemini',
            ['uuid' => $uuid]
        );

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function findUrls($uri)
    {
        $query =
          'SELECT fedora_uri as uri FROM Gemini WHERE drupal_uri = :uri union
            SELECT drupal_uri as uri FROM Gemini WHERE fedora_uri = :uri';
        return $this->connection->fetchAssoc(
            $query,
            ['uri' => $uri]
        );
    }
}
