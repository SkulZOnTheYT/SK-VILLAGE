<?php

declare(strict_types=1);

namespace SkulZOnTheYT\SKV\populator;

use pocketmine\Server;
use pocketmine\world\ChunkManager;
use pocketmine\world\biome\PlainBiome;
use pocketmine\world\biome\DesertBiome;
use pocketmine\world\biome\SwampBiome;
use pocketmine\world\biome\TaigaBiome;
use pocketmine\world\biome\IcePlainsBiome;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\noise\Simplex;
use SkulZOnTheYT\SKV\SKVillages;
use SkulZOnTheYT\SKV\math\BoundingBox;
use SkulZOnTheYT\SKV\scheduler\CallbackableChunkGenerationTask;
use SkulZOnTheYT\SKV\structure\StructurePiece;
use SkulZOnTheYT\SKV\structure\StructureStart;
use SkulZOnTheYT\SKV\structure\VillagePieces;

class PopulatorVillage extends Populator {

    protected const SIZE = 0;
    protected const SPACING = 32;
    protected const SEPARATION = 8;

    protected $isNukkitGenerator;

    public function __construct(bool $isNukkitGenerator) {
        $this->isNukkitGenerator = $isNukkitGenerator;
    }

    public function populate(ChunkManager $level, int $chunkX, int $chunkZ, NukkitRandom $random, FullChunk $chunk): void {
        // VillageFeature::isFeatureChunk(BiomeSource const &,Random &,ChunkPos const &,uint)
        $biome = $chunk->getBiomeId(7, 7);
        if (in_array($biome, [new PlainBiome(), new DesertBiome(), new IcePlainsBiome(), new SwampBiome(), new TaigaBiome()])) {
            $seed = $level->getSeed();
            $cX = ($chunkX < 0 ? $chunkX - (self::SPACING - 1) : $chunkX) / self::SPACING;
            $cZ = ($chunkZ < 0 ? $chunkZ - (self::SPACING - 1) : $chunkZ) / self::SPACING;
            $random->setSeed($cX * 0x4f9939f508 + $cZ * 0x1ef1565bd5 + $seed + 0x9e7f70);

            if ($chunkX == $cX * self::SPACING + $random->nextBoundedInt(self::SPACING - self::SEPARATION) && $chunkZ == $cZ * self::SPACING + $random->nextBoundedInt(self::SPACING - self::SEPARATION)) {
                // VillageFeature::createStructureStart(Dimension &,Random &,ChunkPos const &)
                $start = new VillageStart($level, $chunkX, $chunkZ, $this->isNukkitGenerator);
                $start->generatePieces($level, $chunkX, $chunkZ);

                if ($start->isValid()) { // TODO: serialize nbt
                    $random->setSeed($seed);
                    $r1 = $random->nextInt();
                    $r2 = $random->nextInt();

                    $boundingBox = $start->getBoundingBox();
                    for ($cx = $boundingBox->x0 >> 4; $cx <= $boundingBox->x1 >> 4; $cx++) {
                        for ($cz = $boundingBox->z0 >> 4; $cz <= $boundingBox->z1 >> 4; $cz++) {
                            $rand = new NukkitRandom($cx * $r1 ^ $cz * $r2 ^ $seed);
                            $x = $cx << 4;
                            $z = $cz << 4;
                            $ck = $level->getChunk($cx, $cz);
                            if ($ck === null) {
                                $ck = $chunk->getProvider()->getChunk($cx, $cz, true);
                            }

                            if ($ck->isGenerated()) {
                                $start->postProcess($level, $rand, new BoundingBox($x, $z, $x + 15, $z + 15), $cx, $cz);
                            } else {
                                $f_cx = $cx;
                                $f_cz = $cz;
                                Server::getInstance()->getScheduler()->scheduleAsyncTask(null, new CallbackableChunkGenerationTask(
                                    $chunk->getProvider()->getLevel(), $ck, $start,
                                    function ($structure) use ($level, $rand, $x, $z, $f_cx, $f_cz) {
                                        $structure->postProcess($level, $rand, new BoundingBox($x, $z, $x + 15, $z + 15), $f_cx, $f_cz);
                                    }
                                ));
                            }
                        }
                    }

                    ClassicVillagePlugin::debug(static::class, $chunkX << 4, $chunkZ << 4);
                }
            }
        }
    }
    
    public static function byId(int $id): Type {
        $values = self::values();
        if ($id < 0 || $id >= count($values)) {
            return Type::PLAINS;
        }
        return $values[$id];
    }
}

class VillageStart extends StructureStart {

    private $valid;
    private $isNukkitGenerator;

    public function __construct(ChunkManager $level, int $chunkX, int $chunkZ, bool $isNukkitGenerator) {
        parent::__construct($level, $chunkX, $chunkZ);
        $this->isNukkitGenerator = $isNukkitGenerator;
    }

    public function generatePieces(ChunkManager $level, int $chunkX, int $chunkZ): void {
        $start = new VillagePieces\StartPiece($level, 0, $this->random, ($chunkX << 4) + 2, ($chunkZ << 4) + 2, VillagePieces::getStructureVillageWeightedPieceList($this->random, PopulatorVillage::SIZE), PopulatorVillage::SIZE, $this->isNukkitGenerator);
        $this->pieces[] = $start;
        $start->addChildren($start, $this->pieces, $this->random);

        $pendingRoads = $start->pendingRoads;
        $pendingHouses = $start->pendingHouses;
        while (!empty($pendingRoads) || !empty($pendingHouses)) {
            if (empty($pendingRoads)) {
                $pendingHouses[array_rand($pendingHouses)]->addChildren($start, $this->pieces, $this->random);
            } else {
                $pendingRoads[array_rand($pendingRoads)]->addChildren($start, $this->pieces, $this->random);
            }
        }

        $this->calculateBoundingBox();

        $houseCount = 0;
        foreach ($this->pieces as $piece) {
            if (!$piece instanceof VillagePieces\Road) {
                ++$houseCount;
            }
        }
        $this->valid = $houseCount > 2;
    }

    public function isValid(): bool {
        return $this->valid;
    }

    public function getType(): string {
        return "Village";
    }
}
