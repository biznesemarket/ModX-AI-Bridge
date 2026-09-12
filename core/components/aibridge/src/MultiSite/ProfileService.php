<?php
declare(strict_types=1);
namespace AIBridge\MultiSite;

use MODX\Revolution\modX;

final class ProfileService
{
    public function __construct(private readonly modX $modx) {}

    public function get(int $id): ?ProfileContext
    {
        $row=$this->modx->getObject(\AIBridge\Model\Profile::class,$id);
        return $row ? $this->map($row->toArray()) : null;
    }
    public function getBySiteKey(string $siteKey): ?ProfileContext
    {
        $row=$this->modx->getObject(\AIBridge\Model\Profile::class,['site_key'=>$siteKey]);
        return $row ? $this->map($row->toArray()) : null;
    }
    public function requireActive(int $id): ProfileContext
    {
        $profile=$this->get($id);
        if (!$profile) throw new \RuntimeException('Bridge profile not found.');
        if (!$profile->active()) throw new \RuntimeException('Bridge profile is inactive.');
        return $profile;
    }
    public function create(array $input): ProfileContext
    {
        $siteKey=trim((string)($input['site_key']??''));
        $name=trim((string)($input['name']??''));
        if ($siteKey===''||$name==='') throw new \InvalidArgumentException('Profile name and site_key are required.');
        if ($this->getBySiteKey($siteKey)) throw new \RuntimeException('Profile site_key already exists.');
        $row=$this->modx->newObject(\AIBridge\Model\Profile::class);
        $row->fromArray(['name'=>$name,'site_key'=>$siteKey,'status'=>$input['status']??'active','environment'=>$input['environment']??'production','base_url'=>$input['base_url']??null,'metadata_json'=>json_encode($input['metadata']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        if(!$row->save()) throw new \RuntimeException('Profile persistence failed.');
        return $this->map($row->toArray());
    }
    private function map(array $row): ProfileContext
    {
        $metadata=json_decode((string)($row['metadata_json']??'{}'),true);
        return new ProfileContext((int)$row['id'],(string)$row['site_key'],(string)$row['name'],(string)$row['environment'],(string)$row['status'],($row['base_url']??null)?:null,is_array($metadata)?$metadata:[]);
    }
}
