<?php

namespace App\Module\ModuleListOfListings;

use App\HelpersClasses\Tr\Tr;
use App\Models\Listing;
use App\Models\ListingRelation;
use App\Models\ListingType;
use App\Models\Module;
use App\Models\ModuleData;
use App\Models\Settings;
use App\Module\CmsModule;
use Illuminate\Support\Facades\DB;

class ModuleListOfListings extends CmsModule
{
    public static $folder = 'plugins';
    
    public $name = 'ListOfListings';
    
    public $alias = 'ModuleListOfListings';
    
    public $meta_fields = [];
    
    public $settings_fields = ['count', 'listingType', 'timeDuration', 'rel', 'criteria'];
    
    public function getAdminViewType()
    {

        $listing_types = ListingType::where('flag', '0')->select('id','name')->get();

        return view('ModuleListOfListings.view.type')->with([
            'settings' => $this->getSettings(),
            'listing_types' => $listing_types,
        ]);
    }
    
    public function getAdminViewEdit()
    {
        $listing = $this->getListing();
        $connected = Listing::where('parent_id', $listing->id)->where('revision_id', '!=', 0)
        ->select('name', 'id', 'listing_type_id')->get();
//        return 'It is List of listings plugin. He show on front list of listings by some criteria.';
        return view('ModuleListOfListings.view.edit')->with([
            'settings' => $this->getSettings(),
            'connected' => $connected,
        ]);
    }
    
    public function processQuery($data)
    {
        $this->updateSettings($data);
    }
    
    public function processEditQuery($data)
    {
        $this->updateMeta($data);
    }
    
    public function getFrontendHtml()
    {
        $settings = $this->getSettings();
        $listing_type = ListingType::find($settings['listingType']);
        if(!$listing_type) return '';
        $template_structure = json_decode($listing_type->template_catalog, 1);

        $metrics_in_structure = Listing::getMetricsFromStructure($template_structure);


        $pr = DB::getTablePrefix();

        $listings_db_query = DB::table('listing');
        $listings_db_query->leftJoin('listing_type', 'listing_type.id', '=', 'listing.listing_type_id');
//        $listings_db_query->select(DB::raw($pr.'listing.*, '.$pr.'listing_type.url'.Tr::getPMainLang().' AS listing_type_url'));
        $listings_db_query->where('listing.revision_id', '0');
        $listings_db_query->where('listing.is_active', '1');
        $listings_db_query->where(['listing.type' => 'listing', 'listing.listing_type_id' => $listing_type->id]);

        if($settings['criteria'] == 'comments') { //=====================================================================
            $listings_db_query->rightJoin('comments', 'listing.id', '=', 'comments.listing_id');
            $listings_db_query->select(DB::raw($pr.'listing.*, '.$pr.'listing_type.url'.Tr::getPMainLang().' AS listing_type_url'),
                DB::raw('count(comments.email) as commentsCount'));
            $listings_db_query->whereBetween('comments.created_at', array(date('Y-m-d h:i:s', strtotime('-'.$settings['timeDuration'].'days')), date('Y-m-d  h:i:s', strtotime('+1days'))));
            $listings_db_query->groupBy('comments.listing_id');
            $listings_db_query->orderBy('commentsCount', 'DESC');

        }elseif($settings['criteria'] == 'ratings'){ //==================================================================
            $listings_db_query->rightJoin('rating_values', 'listing.id', '=', 'rating_values.listing_id');
            $listings_db_query->select(DB::raw($pr.'listing.*, '.$pr.'listing_type.url'.Tr::getPMainLang().' AS listing_type_url'),
                                        DB::raw('sum(value) as valueCount'));
            $listings_db_query->groupBy('rating_values.listing_id');
            $listings_db_query->orderBy('valueCount', 'DESC');

        }elseif($settings['criteria'] == 'latest'){ //===================================================================
            $current_listing = $this->getListing();
            if(!$current_listing) return '';
            if($current_listing->revision_id != 0){
                $parent_id = $current_listing->revision_id;
            }else{
                $parent_id = $current_listing->id;
            }
            $listing_ids = Listing::where(['type'=>'listing', 'parent_id'=>$parent_id, 'revision_id'=>0])->lists('id')->toArray();

            $listings_db_query->whereIn('listing.id', $listing_ids);
            $listings_db_query->select(DB::raw($pr.'listing.*, '.$pr.'listing_type.url'.Tr::getPMainLang().' AS listing_type_url'));
            $listings_db_query->orderBy('listing.created_at', 'DESC');

        }else { //if criteria == similar ================================================================================
            $listing_tmp =$this->getListing();
            if($listing_tmp->revision_id == 0){
                $listing_id = $listing_tmp->id;
            }else{
                $listing_id = $listing_tmp->revision_id;
            }
            $listing_type_id = $this->getListing()->listing_type_id;
            $listing_type = ListingType::find($listing_type_id);
            $relation_ids = ListingRelation::where('listing_id', '=', $listing_id)->lists('relation_id')->toArray();
            $listing_ids = ListingRelation::where('listing_id', '<>', $listing_id)->whereIn('relation_id', $relation_ids)
                ->lists('listing_id')->toArray();

            $listings_db_query->whereIn('listing.id', $listing_ids);
            $listings_db_query->select(DB::raw($pr.'listing.*, '.$pr.'listing_type.url'.Tr::getPMainLang().' AS listing_type_url')); // todo
            $listings_db_query->orderBy('listing.created_at', 'ASC');
        }

        $listings = $listings_db_query->take($settings['count'])->get();

        $listing_ids = [];
        foreach($listings as $li)
        {
            $listing_ids[] = $li->id;
        }

        $metrics_data_sel = DB::table('module');
        $metrics_data_sel->select(DB::raw($pr.'module.*'));
        $metrics_data_sel->whereIn('module.metric_id', $metrics_in_structure);
        $metrics_data_sel->leftJoin('metric', 'module.metric_id', '=', 'metric.id');
        $metrics_data_sel->leftJoin('listing_metric', 'listing_metric.metric_id', '=', 'metric.id');
        $metrics_data_sel->where('listing_metric.is_active', 1);
        $metrics_data_sel->orWhere('listing_metric.is_active', 'IS', 'NULL');
        $metrics_data_sel->orderBy('module.pos', 'ASC');

        $metrics_data_db = $metrics_data_sel->get();

        $metrics_data = [];
        $modules_ids = [];
        foreach($metrics_data_db as $mdb)
        {
            if(!array_key_exists($mdb->metric_id, $metrics_data))
                $metrics_data[$mdb->metric_id] = [];

            $metrics_data[$mdb->metric_id][$mdb->id] = (array)$mdb;
            $modules_ids[] = $mdb->id;
        }


        $contents_data = ModuleData::whereIn('listing_id', $listing_ids)->whereIn('module_id', $modules_ids)->get()->toArray();
        $contents = array();
        foreach($contents_data as $cd)
        {
            if(!array_key_exists($cd['listing_id'], $contents))
            {
                $contents[$cd['listing_id']] = array();
            }
            if(!array_key_exists($cd['module_id'], $contents[$cd['listing_id']]))
            {
                $contents[$cd['listing_id']][$cd['module_id']] = array();
            }
            $contents[$cd['listing_id']][$cd['module_id']][$cd['module_key']] = $cd;
        }

        $modules_data = [];
        foreach($listings as $listing)
        {
            $module_d = $metrics_data;

            foreach($module_d as $metric_id => $mod)
            {
                foreach($mod as $mod_id => $mod_data)
                {
                    $module_d[$metric_id][$mod_id]['data'] = (isset($contents[$listing->id][$mod_id])) ? $contents[$listing->id][$mod_id] : [];
                }
            }

            $modules_data[$listing->id] = $module_d;
        }

        if(!$path = Settings::is_template($this->alias, 1)){
            $path = $_SERVER['DOCUMENT_ROOT'] . '/app/module/ModuleListOfListings/view/front.blade.php';
        }

        return view()->file($path)->with([
            'listing_type' => $listing_type,
            'listings' => $listings,
            'modules_data' => $modules_data,
            'tpl_structure' => $template_structure,
            'title'=>$this->field_data['name'.Tr::getPMainLang()],
            'rel' => $settings['rel'],
            'list_listings' => [] // ????????????????????
        ]);
    }

    public function onPublish($copy_listing_id, $original_listing_id, $copy_module_id, $original_module_id, $language)
    {
        $lang = ($language == '') ? '' : $language;
        $copy_module = Module::find($copy_module_id);
        $original_module = Module::find($original_module_id);
        $original_module->{'module_settings'.$lang} = $copy_module->{'module_settings'.$lang};
        $original_module->save();
    }

    public function getSidebarViewEdit() {

        $listing_types = ListingType::where('flag', '0')->select('id','name')->get();

        return view('ModuleListOfListings.view.sidebaredit')->with([
            'settings' => $this->getSettings(),
            'listing_types' => $listing_types,
        ]);
    }

    public function getSidebarFrontendHtml()
    {
        return $this->getFrontendHtml();
    }
}
