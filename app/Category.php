<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Cache;

class Category extends Model
{
    const COMMON_TYPE       = 1; //common ads type
    const REAL_ESTATE_TYPE  = 2; //real estate ads
    const CARS_TYPE         = 3; //cars ads
    const SERVICES_TYPE     = 4; //services ads
    const CLOTHES_TYPE      = 5; //clothes ads
    const SHOES_TYPE        = 6; //shoes ads
    const REAL_ESTATE_LAND_TYPE = 7; //real estate land ads
    const JOBS_TYPE         = 8; //jobs ads

    protected $table        = 'category';
    protected $primaryKey   = 'category_id';
    public $timestamps      = false;
    
    protected $fillable = ['category_parent_id', 'category_type', 'category_title', 
        'category_slug', 'category_description', 'category_keywords', 'category_img', 'category_active', 'category_ord'];
    
    public function parents()
    {
        return $this->belongsTo('App\Category', 'category_parent_id');
    }
    
    public function children()
    {
        return $this->hasMany('App\Category', 'category_parent_id');
    }
    
    public function getAllHierarhy($_parent_id = null, $_level = 0, $_active = 1)
    {
        $ret = [];
        $_level++;

        $query = $this->where('category_parent_id', $_parent_id)
                                ->with('children')
                                ->orderBy('category_ord', 'asc');

        if($_active){
            $query->where('category_active', '=', 1);
        }

        $categoryCollection = $query->get();

        if(!$categoryCollection->isEmpty()){
            foreach ($categoryCollection as $k => $v){
                $ret[$v->category_id] = ['cid' => $v->category_id,
                    'title'         => $v->category_title,
                    'level'         => $_level,
                    'category_type' => $v->category_type,
                    'ord'           => $v->category_ord,
                    'slug'          => $v->category_slug,
                    'active'        => $v->category_active,
                    'ad_count'      => Ad::where('category_id', $v->category_id)->count()
                ];

                if($v->children->count() > 0){
                    $ret[$v->category_id]['c'] = $this->getAllHierarhy($v->category_id, $_level, $_active);
                }
            }
        }
        return $ret;
    }
    
    public function getAllHierarhyFlat($_parent_id = null, $_level = 0)
    {
        $ret = [];
        $_level++;
        $categoryCollection = $this->where('category_parent_id', $_parent_id)
            ->where('category_active', '=', 1)
            ->with('children')
            ->orderBy('category_ord', 'asc')
            ->get();
         
        if(!$categoryCollection->isEmpty()){
            foreach ($categoryCollection as $k => $v){
                $ret[$v->category_id] = ['cid' => $v->category_id,
                    'title' => $v->category_title,
                    'level' => $_level,
                    'category_type' => $v->category_type];

                if($v->children->count() > 0){
                    $ret = array_merge($ret, $this->getAllHierarhyFlat($v->category_id, $_level));
                }
            }
        }
        return $ret;
    }
    
    public function getOneLevel($_parent_id = null)
    {
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        return Cache::rememberForever($cacheKey, function() use ($_parent_id) {
            return $this->where('category_parent_id', $_parent_id)
                ->where('category_active', 1)
                ->orderBy('category_ord', 'asc')
                ->get();
        });
    }
    
    public function getIdBySlug($_slug)
    {
        $ret = 0;
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $res = Cache::rememberForever($cacheKey, function() use ($_slug) {
            return $this->select('category_id')
                ->where('category_slug', $_slug)
                ->first();
        });
        if(!empty($res)){
            $ret = $res->category_id;
        }
        return $ret;
    }
    
    public function getSlugById($_category_id)
    {
        $ret = '';
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $res = Cache::rememberForever($cacheKey, function() use ($_category_id) {
            return $this->select('category_slug')
                ->where('category_id', $_category_id)
                ->first();
        });
        if(!empty($res)){
            $ret = $res->category_slug;
        }
        return $ret;
    }
    
    public function getParentsBySlug($_slug)
    {
        $ret = [];
        $categoryCollection = $this->where('category_slug', $_slug)
                                ->with('parents')
                                ->first();

        //get parents
        if(!empty($categoryCollection)){
            $ret[$categoryCollection->category_id] = $categoryCollection->attributes;
            if(!empty($categoryCollection->parents)){
                $ret[$categoryCollection->category_id]['parent'] = $this->getParentsBySlug($categoryCollection->parents->category_slug);
            }
        }
        return $ret;
    }
    
    public function getParentsById($_category_id)
    {
        $ret = [];
        $categoryCollection = $this->where('category_id', $_category_id)
                                ->with('parents')
                                ->first();

        //get parents
        if(!empty($categoryCollection)){
            $ret[$categoryCollection->category_id] = $categoryCollection->attributes;
            if(!empty($categoryCollection->parents)){
                $ret[$categoryCollection->category_id]['parent'] = $this->getParentsById($categoryCollection->parents->category_id);
            }
        }
        return $ret;
    }
    
    public function getParentsBySlugFlat($_slug)
    {
        $ret = [];
        do{
            $categoryCollection = $this->where('category_slug', $_slug)->with('parents')->first();
            $ret[$categoryCollection->category_id] = $categoryCollection->attributes;
            $ret[$categoryCollection->category_id]['category_full_path'] = $this->getCategoryFullPathById($_category_id);
            if(!empty($categoryCollection->parents)){
                $_slug = $categoryCollection->parents->category_slug;
            }
        } while ( !empty($categoryCollection) && !empty($categoryCollection->parents));
        return $ret;
    }
    
    public function getParentsByIdFlat($_category_id)
    {
        $ret = [];
        do{
            $categoryCollection = $this->where('category_id', $_category_id)->with('parents')->first();
            if(!empty($categoryCollection)){
                $ret[$categoryCollection->category_id] = $categoryCollection->attributes;
            }
            if(!empty($categoryCollection->parents)){
                $_category_id = $categoryCollection->parents->category_id;
            }
        } while ( !empty($categoryCollection) && !empty($categoryCollection->parents));
        return $ret;
    }
    
    public function getInfoBySlug($_slug)
    {
        $ret = [];
        $categoryCollection = $this->where('category_slug', $_slug)->first();
        if(!empty($categoryCollection)){
            $ret = $categoryCollection->attributes;
        }
        return $ret;
    }
    
    public function getInfoById($_category_id)
    {
        $ret = [];
        $categoryCollection = $this->where('category_id', $_category_id)->first();
        if(!empty($categoryCollection)){
            $ret = $categoryCollection->attributes;
        }
        return $ret;
    }
    
    public function getCategoryFullPathById($_category_id)
    {
        $ret = '';
        $parentCategories = $this->getParentsByIdFlat($_category_id);
        if(!empty($parentCategories)){
            $parentCategories = array_reverse($parentCategories);
            $ret_array = array();
            foreach ($parentCategories as $k => $v){
                $ret_array[] = $v['category_slug'];
            }
            if(!empty($ret_array)){
                $ret = join('/', $ret_array);
            }
        }
        return $ret;
    }
    
    public function getCategoryIdByFullPath($_path)
    {
        $ret = 0;
        $path_parts_array = explode('/', trim($_path, ' /'));
        if(is_array($path_parts_array)){
            $last_category_slug = array_pop($path_parts_array);
            $category_id = $this->getIdBySlug($last_category_slug);
            if($category_id > 0){
                $ret = $category_id;
            }
        }
        return $ret;
    }
}