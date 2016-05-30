<?php

namespace App\Http\Controllers\Admin;

use App\HelpersClasses\PermUsers\CheckPerm;
use App\HelpersClasses\Utils\Utils;
use App\Models\Deliver;
use App\Models\Languages;
use App\Models\Listing;
use App\Models\ListingType;
use App\Models\Module;
use App\Models\ModuleData;
use App\Models\Settings;
use App\Models\Subscribe;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;

class DeliveriesController extends BackController
{

    public function __construct()
    {
        parent::__construct();
        view()->share(['active_admin_menu' => 'settings-templates']);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        CheckPerm::checkAction('view', 'deliveries');
        $templates = Deliver::all();
        $ListingTypes = ListingType::where('flag', 0)->lists('name', 'id')->toArray();
        $listings = Listing::where('type', 'listing')->lists('name', 'id')->toArray();

        return view('admin.deliveries.index')->with(['ListingTypes'=>$ListingTypes,
            'listings'=>$listings,
            'templates'=>$templates,
        ]);
    }

    public function add(Request $request)
    {
        CheckPerm::checkAction('edit', 'deliveries');
        if ($request->isMethod('post')) {

            $new_template = new Deliver();
            $new_template->title = $request->input('template_title');
            $new_template->subject = $request->input('template_subject');
            $new_template->listing_type_id = $request->input('listing-type-id');
            $new_template->listing_id = $request->input('select-listing');
            $new_template->content = $request->input('template_content');

            $new_template->save();

            if($request->input('save')) {
                return redirect('/admin/deliveries/templates');
            } elseif($request->input('save+new')){
                return redirect('/admin/deliveries/template/add');
            }
        }
        $allListingTypes = ListingType::where('flag', 0)->lists('name', 'id')->toArray();
        $languages = Languages::get();
        return view('admin.deliveries.add')->with(['allListingTypes' => $allListingTypes, 'languages' => $languages]);
    }

    public function delete($id)
    {
        CheckPerm::checkAction('delete', 'deliveries');
        Deliver::where('id', $id)->delete();
        return redirect()->back();
    }

    public function edit($id, Request $request)
    {
        CheckPerm::checkAction('edit', 'deliveries');
        $template = Deliver::find($id);
        $allListingTypes = ListingType::where('flag', 0)->lists('name', 'id')->toArray();
        $listings = Listing::where('listing.listing_type_id', $template->listing_type_id)
            ->leftJoin('listing AS copy', 'listing.id', '=', 'copy.revision_id')
            ->where('copy.name', '!=', '')
            ->where('listing.revision_id', '=', 0)
            ->lists('copy.name', 'listing.id')
            ->toArray();
        if ($request->isMethod('post')) {

            $template->subject = $request->input('template_subject');
            $template->title = $request->input('template_title');
            $template->listing_type_id = $request->input('listing-type-id');
            $template->listing_id = $request->input('select-listing');
            $template->content = $request->input('template_content');

            $template->save();

            if($request->input('save')) {
                return redirect('/admin/deliveries/templates');
            } elseif($request->input('save+new')){
                return redirect('/admin/deliveries/template/add');
            }
        }
        $languages = Languages::get();

        return view('admin.deliveries.edit')->with([
            'listings'=>$listings,
            'allListingTypes'=>$allListingTypes,
            'template' => $template,
            'languages' => $languages
        ]);
    }

    public function send_test_message2(){

        //here must be code which take data for template
        $data['content'] = $_GET['content'];

        Mail::send('deliveries.test_message', $data, function($message)
        {
            $message->from('admin@cms-casino.com', 'Super Admin');
            $message->to($_GET['email'], 'user')->subject('Message width deliver template');
        });
        return 1;
    }

    public function send_test_message(){
        $data = Input::all();
        if($data['lang'] != ''){
            $data['lang'] = '_'. $data['lang'];
        }
        //deliveries start
//        $wapi = new WidgetApi();

        if($data['listing_type_id'] == 0 && $data['listing_id'] == 0){
            $types = ListingType::where('flag', 0)->get();//get all listing types
            foreach($types as $type){
                $listing_types[] = Listing::where('listing_type_id', $type->id)->get();
            }
        }elseif($data['listing_id'] == 0){
            $listing_types[0] = Listing::where('listing_type_id', $data['listing_type_id'])->get();//get listings for this listing type
        }else{
            $listing_types[0][0] = Listing::find($data['listing_id']);
        }

        foreach($listing_types as $listing_type){
            foreach($listing_type as $listing){
                //get for this listing his children
                $listing->children = Listing::
                whereBetween('created_at', array(date('Y-m-d h:i:s', strtotime('-'.$data['time_duration'].'days')), date('Y-m-d h:i:s', strtotime('+1days'))))
                    ->where(['parent_id'=> $listing->id, 'revision_id'=>0])
                    ->where('name'.$data['lang'], '!=', '')
                    ->get();

                if(isset($listing->children[0])) {
                    foreach ($listing->children as $child) {
//                        $child->data = ZWidgetsPluginsData::leftJoin('z_widgets_plugins', 'z_widgets_plugins_data.widget_plugin_id', '=', 'z_widgets_plugins.id')
//                            ->where(['listing_id' => $child->id, 'show_in_delivery' => 1])->orderBy('pos')
//                            ->select('z_widgets_plugins.type', 'z_widgets_plugins_data.content'.$data['lang'])->get();
                        $contents_data = ModuleData::where('listing_id', $child->id)->get()->toArray();
                        $contents = array();
                        foreach($contents_data as $cd)
                        {
                            if(!array_key_exists($cd['module_id'], $contents))
                            {
                                $contents[$cd['module_id']] = array();
                            }
                            $contents[$cd['module_id']][$cd['module_key']] = $cd;
                        }

                        $modules = Module::where(['listing_type_id'=> $child->listing_type_id, 'show_in_delivery'=>1])
                            ->orderBy('pos', 'ASC')->get()->toArray();
                        $child_listing_type = ListingType::find($child->listing_type_id);
                        $mods = array();
                        foreach($modules as $mod)
                        {
                            $mod['data'] = (isset($contents[$mod['id']])) ? $contents[$mod['id']] : '';
                            $mod['obj'] = Utils::getModule($mod['module_type'])->setListingType($child_listing_type)->setListing($child)->setFieldData($mod);
                            $mods[] = $mod;
                        }
                        $child->modules = $mods;
                    }
                }
//                else return 0;
            }
        }

        //select template ==============================================================================================

        File::put('../resources/views/admin/deliveries/message.blade.php', $data['content']);
        $lang_content = 'content' . $data['lang'];
        Mail::send('admin/deliveries.message', ['listing_types'=>$listing_types, 'lang_content'=>$lang_content], function($message) use ($data)
        {
            $message->from('info@casino_portal.com', 'PORTAL CASINO');
            $message->to($data['email'], 'Test')->subject($data['subject']);

        });
        return 1;
        //deliveries end
    }

    public function test_deliveries (){

        //deliveries start
        $settings = Settings::where('name', 'like', 'deliveries_%')->lists('value', 'name')->toArray();
        $settings['deliveries_skip_msg'] = (isset($settings['deliveries_skip_msg'])) ? $settings['deliveries_skip_msg'] : 0;
        $settings['deliveries_date'] = (isset($settings['deliveries_date'])) ? $settings['deliveries_date'] : date('Y-m-d', strtotime('-1days'));
        $subscribers = Subscribe::skip($settings['deliveries_skip_msg'])->take($settings['deliveries_count_msg'])->groupBy('type')->groupBy('list_id')->get();

        if($settings['deliveries_date'] == date('Y-m-d') && $settings['deliveries_skip_msg'] == 0)
            return 1;

        if(!$settings_skip = Settings::where('name', 'deliveries_skip_msg')->first()){
            $settings_skip = new Settings();
            $settings_skip->name = 'deliveries_skip_msg';
        }
        $settings_skip->value = $settings['deliveries_skip_msg'] = isset($subscribers[$settings['deliveries_count_msg']-1]) ? $settings['deliveries_skip_msg'] + $settings['deliveries_count_msg'] : 0;
        $settings_skip->save();
        //update settings of day sending start
        if(!$settings_date = Settings::where('name', 'deliveries_date')->first()){
            $settings_date = new Settings();
            $settings_date->name = 'deliveries_date';
        }
        $settings_date->value = date('Y-m-d');
        $settings_date->save();
        //update settings of day sending end


        $tmp_type = null;
        $tmp_list_id = null;
        $wapi = new WidgetApi();
        if(isset($subscribers[0])){
            foreach($subscribers as $subscriber){

                //select time duration start
                if($subscriber->day == 7 && $settings['deliveries_day_weekly']==date('w')){
                    $time_duration = 7;
                }elseif($subscriber->day == 30 && $settings['deliveries_day_monthly']==date('j')){
                    $time_duration = 30;
                }elseif($subscriber->day == 365 && $settings['deliveries_month_yearly']==date('j') && $settings['deliveries_month_yearly']==date('n')){
                    $time_duration = 365;
                }elseif($subscriber->day == 1){
                    $time_duration = 1;
                }else continue;
                //select time duration start

                if(!($subscriber->type == $tmp_type && $subscriber->list_id == $tmp_list_id)){

                    //here we must get data
                    if($subscriber->type == 'portal'){
                        $types = ListingType::where('flag', 0)->get();//get all listing types
                        foreach($types as $type){
                            $listing_types[] = ZListings::where('listing_type_id', $type->id)->get();
                        }
                    }elseif($subscriber->type == 'listing_type'){
                        $listing_types[0] = ZListings::where('listing_type_id', $subscriber->list_id)->get();//get listings for this listing type
                    }else{
                        $temp = ZListings::find($subscriber->list_id);
                        if($temp->type == 'category'){
                            $relShips = ZTaxonomyRelShip::where('taxonomy_id', $temp->id)->lists('listing_id')->toArray();
                            $listing_types[0] = ZListings::whereIn('id', $relShips)->get();
                        }else{
                            $listing_types[0][0] = $temp;
                        }
                    }

                    foreach($listing_types as $listing_type){
                        foreach($listing_type as $listing){
                            //get for this listing his children
                            $listing->children = ZListings::
                                whereBetween('created_at', array(date('Y-m-d h:i:s', strtotime('-'.$time_duration.'days')), date('Y-m-d h:i:s', strtotime('+1days'))))
                                ->where('parent_id', $listing->id)
                                ->where('title'.$subscriber->lang, '!=', '')
                                ->get();

                            if(isset($listing->children[0])) {
                                foreach ($listing->children as $child) {
                                    $child->data = ZWidgetsPluginsData::leftJoin('z_widgets_plugins', 'z_widgets_plugins_data.widget_plugin_id', '=', 'z_widgets_plugins.id')
                                        ->where(['listing_id' => $child->id, 'show_in_delivery' => 1])->orderBy('pos')
                                        ->select('z_widgets_plugins.type', 'z_widgets_plugins_data.content')->get();
                                }
                            }
                        }
                    }

                    //select template ==============================================================================================
                    if($subscriber->type == 'listing' && $template_obj = Deliver::where('listing_id', $subscriber->list_id)->first()){
                        //select template
                    }elseif($subscriber->type == 'listing') {
                        $listing = ZListings::find($subscriber->list_id);
                        if($template_obj = Deliver::where('listing_type_id', $listing->listing_type_id)->first()){
                            //select template
                        }else{
                            $template_obj = Deliver::where(['listing_id' => 0, 'listing_type_id' => 0])->first();
                        }
                    }elseif($subscriber->type == 'listing_type' && $template_obj = Deliver::where('listing_type_id', $subscriber->list_id)->first()){
                        //select template
                    }else{
                        $template_obj = Deliver::where(['listing_id' => 0, 'listing_type_id' => 0])->first();
                    }
                }
                $tmp_type = $subscriber->type;
                $tmp_list_id = $subscriber->list_id;

//                return view('deliveries.message', ['listing_types'=>$listing_types, 'wapi'=>$wapi]);

                File::put('../resources/views/deliveries/message.blade.php', $template_obj->content);
                $lang_content = 'content' . $subscriber->lang;
                Mail::send('deliveries.message', ['listing_types'=>$listing_types, 'wapi'=>$wapi, 'lang_content' => $lang_content], function($message) use ($subscriber, $template_obj)
                {
                    $message->from('deliveries@gmail.com', 'PORTAL CASINO');
                    $message->to($subscriber->email, $subscriber->name)->subject($template_obj->subject);
                });
            }
        }
        //deliveries end
    }

}
