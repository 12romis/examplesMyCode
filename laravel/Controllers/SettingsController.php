<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\BackController;
use App\Models\Module;
use App\Models\ModuleSidebar;
use Illuminate\Http\Request;
use App\Models\Settings;
use App\Models\ListingType;
use App\Models\Listing;
use App\Models\Sidebars;
use App\Models\Languages;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use DB;

class SettingsController extends BackController
{
    public function index(Request $request)
    {
        view()->share(['active_admin_menu' => 'settings-main']);
        
        $settings = ['deliveries_day_weekly', 'deliveries_day_monthly', 'deliveries_month_yearly', 'deliveries_day_month_yearly',
            'deliveries_count_msg', 'default_site_template',
            'delimeter-5', 'delimeter-6'];
        
        if ($request->isMethod('post')) {

            foreach($settings as $set)
            {
                if(!$obj = Settings::where('name', $set)->first()){
                    $obj = new Settings();
                    $obj->name = $set;
                }
                $obj->value = ($request->input($set)) ? $request->input($set) : 0;
                $obj->save();
            }            
        }
        $path = $_SERVER['DOCUMENT_ROOT'] . '/app/Themes';
        if(!is_dir($path))
            mkdir($path);
        
        $folders = scandir($path);
        $templates = array();
        foreach($folders as $folder){
            if(!is_dir($folder)){
                $templates[] = $folder;
            }
        }
        
        $sidebars = Sidebars::get();
        
        
        $view_settings = [];
        foreach($settings as $set)
        {
            $view_settings[$set] = Settings::getSetting($set);
        }


        return view('admin.settings.index')->with([
            'templates' => $templates,
            'view_settings' => $view_settings,
            'sidebars' => $sidebars,]);
    }

    public function listingtypes($id = false)
    {
        view()->share(['active_admin_menu' => 'settings-listingtypes']);
        
        $listing_types = ListingType::orderBy('flag', 'ASC')->get();
        
        $listings = false;
        $listing_type = false;
        $listing_type_id = false;
        if($id)
        {
            $listings = Listing::where('listing_type_id', $id)->where('revision_id', '!=', 0)->get();
            
            foreach($listing_types as $lt)
            {
                if($lt->id == $id)
                {
                    $listing_type = $lt;
                    $listing_type_id = $lt->id;
                }
            }
        }
        
        return view('admin.settings.listingtypes')->with(['listing_types' => $listing_types,
            'listings' => $listings,
            'listing_type' => $listing_type,
            'listing_type_id' => $listing_type_id]);
    }

    public function special_offers (Request $request){
        view()->share(['active_admin_menu' => 'settings-special_offers']);
        $language = (isset($_GET['lang'])) ? $_GET['lang'] : '';
        $sLang = $language == '' ? '' : '_'.$language;
        $languages = Languages::get();

        if(isset($_GET['type']) && $_GET['type'] == 'listing_types'){
            $offers =  Module::where('module.module_type', 'ModuleSpecialOffers')->where('module.listing_type_id', '!=', 0)
                ->leftJoin('listing_type', 'module.listing_type_id', '=', 'listing_type.id')
                ->select('module.name'.$sLang.' AS title', 'module.module_settings'.$sLang.' AS plugin_settings',
                    'listing_type.url'.$sLang.' AS type_url', 'listing_type.name'.$sLang.' AS name', 'module.id')
                ->orderBy('module.id', 'DESC')->get();
        }elseif(isset($_GET['type']) && $_GET['type'] == 'sidebars'){
            $offers = ModuleSidebar::where('module_type', 'ModuleSpecialOffers')
                ->leftJoin('sidebars', 'module_sidebar.sidebar_id', '=', 'sidebars.id')
                ->select('module_sidebar.name'.$sLang.' AS title', 'module_sidebar.module_settings'.$sLang.' AS plugin_settings',
                    'sidebars.name'.$sLang.' AS name', 'module_sidebar.id')->get();
        }else{
            $offers = Module::where(['module.module_type' => 'ModuleSpecialOffers', 'module.listing_type_id' => 0, 'listing.revision_id'=>0])
//                ->where('module.name'.$sLang, '!=', '')
                ->leftJoin('listing', 'module.listing_id', '=', 'listing.id')
                ->leftJoin('listing_type', 'listing.listing_type_id', '=', 'listing_type.id')
                ->select('module.name'.$sLang.' AS title', 'module.module_settings'.$sLang.' AS plugin_settings',
                    'listing.name'.$sLang.' AS name', 'listing.url'.$sLang.' AS url',
                    'listing_type.url'.$sLang.' AS type_url', 'module.id')
                ->orderBy('module.id', 'DESC')->get();
        }


        return view('admin.settings.specialOffers')->with([
            'offers' => $offers,
            'languages' => $languages,
            'language' => $language,
        ]);
    }

    public function change_offer (Request $request){
        $id = $request->input('id');

        if($request->input('action') == 'delete'){
            if($request->input('place') == 'sidebars'){
                ModuleSidebar::where('id', $id)->delete();
            }else{
                $module = Module::find($id);
                if($module->listing_type_id == 0){
                    $copy_listing_id = Listing::where('revision_id', $module->listing_id)->value('id');
                    $copy_id = Module::findCopyModuleId(0, $module->listing_id, $copy_listing_id, $module->id);
                    Module::where('id', $copy_id)->delete();
                }
                $module->delete();
            }
        }else{
            $lang = $request->input('lang');
            $prefix = $lang == '' ? 'module_settings' : 'module_settings_'.$lang;
            if($request->input('place') == 'sidebars'){
                $offer = ModuleSidebar::find($id);
            }else{
                $offer = Module::find($id);
            }
            $offer->$prefix = json_encode(['date_start'=>$request->input('date_start'), 'date_end'=>$request->input('date_end')]);
            $offer->save();
            $module = Module::find($id);
            if($module->listing_type_id == 0){
                $copy_listing_id = Listing::where('revision_id', $module->listing_id)->value('id');
                $copy_id = Module::findCopyModuleId(0, $module->listing_id, $copy_listing_id, $module->id);

                $offer_copy = Module::find($copy_id);
                $offer_copy->$prefix = json_encode(['date_start'=>$request->input('date_start'), 'date_end'=>$request->input('date_end')]);
                $offer_copy->save();
            }
        }

        return 1;
    }

    public function wpnames (Request $request){
        view()->share(['active_admin_menu' => 'settings-wpnames']);
        if($request->isMethod('post')){
            $keys = $request->input('key');
            $vals = $request->input('val');
            $count = count($keys);
            $to_file = "<?php return [ \n";
            for ($i=0; $i<$count; $i++){
                $to_file .= "'". $keys[$i] . "' => '" . addslashes($vals[$i]) . "',\n";
            }
            $to_file .= "];";

            File::put('../resources/lang/en/wpnames.php', $to_file);
            Session::flash('success', 'All changes was saved!');
        }

        $translates = include '../resources/lang/en/wpnames.php';

        return view('admin.settings.edit_wp_names')->with([
            'translates' => $translates,
        ]);
    }
    
    public function getAjaxListings(){
        $lpr = (isset($_GET['lang']) && $_GET['lang'] != '') ? '_'.$_GET['lang'] : '';
        
        $listings_q = Listing::where('listing.name'.$lpr, '!=', '')->select('listing.*');
        //echo $_GET['flag'].'<br />';
        //echo $_GET['listing_type_id'].'<br />';
        if(($_GET['flag']*1) > 0)
        {
            $listings_q->leftJoin('listing_type', 'listing.listing_type_id', '=', 'listing_type.id');
            $listings_q->where('listing_type.flag', $_GET['flag']);
            $listings_q->where('listing_type.for_listing_type', $_GET['listing_type_id']);
        }
        else
        {
            $listings_q->where('listing.listing_type_id', $_GET['listing_type_id']);
        }
        $listings_q->where('revision_id', 0);
        
        $data = $listings_q->get();
        
        $return = [];
        
        foreach($data as $d)
        {
            $return[$d->id] = $d->toArray();
        }
        //$casinos = ZListings::where('listing_type_id', $_GET['listing_type_id'])->where('title', '!=', '')->lists('title', 'id')->toArray();
        return (json_encode($return));
    }
}