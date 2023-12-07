<?php

declare(strict_types=1);

namespace SkulZOnTheYT\SKV\scheduler;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;
use pocketmine\scheduler\AsyncTask;

class ChunkPopulationTask extends AsyncTask {

    private $world;
    private $chunk;
    private $populators;

    public function __construct(ChunkManager $world, FullChunk $chunk, array $populators) {
        $this->world = $world;
        $this->chunk = $chunk;
        $this->populators = $populators;
    }

    public function onRun() : void {
        $chunkX = $this->chunk->getX();
        $chunkZ = $this->chunk->getZ();
        $random = new Random(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->world->getSeed());

        foreach ($this->populators as $populator) {
            /** @var Populator $populator */
            $populator->populate($this->world, $chunkX, $chunkZ, $random, $this->chunk);
        }
    }
}
