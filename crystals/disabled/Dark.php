<?php

namespace ClearView;

use ClearView\Shard;
use ClearView\Facet;
use ClearView\Exception;
use ClearView\Mosaic;

/**
 * Crystal for accessing the Tor network via the Stem protocol.
 *
 * The Dark Crystal is a neon-lit gateway to the Tor network, controlling circuits and fetching hidden
 * service data. Registered as 'ClearView::Dark' and stores in inlay: Dark, it exposes components like Skeksi
 * (controller) and Essence (responses) via Mosaic getVar().
 *
 * @see \ClearView\Crystal
 * @see https://stem.torproject.org/api.html
 */
class Dark extends Crystal
{
    /**
     * Initializes the Dark Crystal with a Stem interface.
     *
     * Sets up a Stem instance to manage Tor connections and registers components in Mosaic. Components
     * include:
     * - **Dark::Skeksi**: Controller for Tor network operations, wrapping Stem’s Controller API (e.g., get_info, signal).
     * - **Dark::socket**: Control socket for direct Tor communication, wrapping Stem’s socket API (e.g., send, recv).
     * - **Dark::connection**: Connection manager for Tor control authentication, wrapping Stem’s authentication API (e.g., authenticate).
     * - **Dark::drain**: Process manager for launching the Tor network, wrapping Stem’s process launch API (e.g., launch_tor).
     * - **Dark::essence**: Response handler for Tor network messages, wrapping Stem’s response handling (e.g., content, raw_content). 
     * 		Sent to Dark::Skeksi
     *
     * @param mixed $pwObject The Tor interface object (defaults to a new Stem instance).
     * @throws Exception If the Stem interface reports an error.
     */
    public function __construct($pwObject = null,$panename,$inlayname)
    {
        $stem = $pwObject ?? new Stem($this);
        parent::__construct($stem);
        ClearView::Mosaic()->fill([
            'Dark::Skeksi' 	=> $stem->controller(),
            'Dark::socket' 	=> $stem->socket(),
            'Dark::connection'  => $stem->connection(),
            'Dark::drain' 	=> $stem->process(),
            'Dark::essence' 	=> $stem->response()
        ]);
        if ($stem->error()) {
            throw new Exception("Gelfling {{text20\User::displayname}}, the Skeksis hoard the Crystal’s essence!");
        }
    }

    /**
     * Initializes the Dark Crystal with inlay 'Dark'.
     *
     * Registers the Crystal in Mosaic as 'Crystal::Dark' with inlay 'Dark' for system-wide access.
     *
     * @return void
     */
    public static function init(): void
    {
        parent::init();
        ClearView::Mosaic()->addShard(new self(), id: self::class, inlay: 'Dark');
    }
}

/**
 * @internal
 * Stem interface for interacting with the Tor network.
 *
 * A Stem implementation. Component calls (controller, socket, etc.) return the instance; error() returns false; 
 */
class Stem extends Shard
{
    /** @var string The primary field for storing network responses. */
    protected $primaryField = 'contents';

    /** @var array Component method names that return this instance. */
    protected $components = ['controller', 'socket', 'connection', 'process', 'response'];

    /**
     * Initializes the Stem interface.
     *
     * Sets up the Stem with a Dark Crystal reference and empty contents.
     *
     * @param Dark $dark The parent Dark Crystal.
     */
    public function __construct(Dark $dark)
    {
        parent::__construct(['contents' => []]);
    }

    /**
     * Returns a response from the Skeksi.
     *
     * @param string $field The field name.
     * @return string|null A response string, or null if not found.
     */
    public function getField(string $field)
    {
        return $this->contents[array_rand($this->contents)];
    }

    /**
     * Handles method calls, returning self for components, false for error.
     *
     * @param string $method The called method.
     * @param array $args The arguments.
     * @return Stem|bool|string Self if method is in components, false for error.
     */
    public function __call($method, $args)
    {
        if ($method === 'error') {
            return false;
        }
        if (in_array($method, $this->components)) {
            return $this;
        }
        return $this->contents[array_rand($this->contents)];
    }

    /** @var array Song: I'm Too Skeksi! */
    protected $contents = [
        "I'm too Skeksi for my Crystal, too Skeksi for my Crystal",
        "So Skeksi it hurts",
        "I'm too Skeksi for your Tor, too Skeksi for your Tor",
        "No Gelfling can surf",
        "I'm a Skeksi, you know what I mean",
        "And I drain your essence in the datastream",
        "In the datastream, yeah, in the datastream",
        "I drain your essence in the datastream",
        "I'm too Skeksi for your socket, too Skeksi for your socket",
        "Your commands I spurn",
        "I'm too Skeksi for your key, too Skeksi for your key",
        "Your crypto I burn",
        "I'm a Skeksi, you know what I mean",
        "And I hoard your essence in the datastream",
        "In the datastream, yeah, in the datastream",
        "I hoard your essence in the datastream",
        "I'm too Skeksi for my code, too Skeksi for my code",
        "Too Skeksi by far",
        "I'm too Skeksi for your soul, too Skeksi for your soul",
        "I’ll lock it in my jar",
        "And I’m too Skeksi for this Crystal",
        "Too Skeksi for this Crystal",
        "Too Skeksi, Gelfling!",
        "I'm a Skeksi, you know what I mean",
        "And I crush your essence in the datastream",
        "In the datastream, yeah, in the datastream",
        "I crush your essence in the datastream"
    ];
}
