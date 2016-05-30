<?php

namespace App\Http\Controllers\Admin;

use App\Models\Languages;
use App\Models\Settings;
use App\Models\ZListings;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\ListingType;
use DB;
use App\HelpersClasses\Utils\Utils;
use App\Models\Comment;

class CommentsController extends BackController
{
    public function __construct()
    {
        parent::__construct();
        view()->share(['active_admin_menu' => 'settings-comments']);
    }

    public function comments (Request $request){

        if($request->isMethod('post')){
            $comment_ids = $request->input('comments', []);
            $action = $request->input('actions');
            if($action == 'delete'){
                Comment::whereIn('id', $comment_ids)->delete();
            }else{
                foreach($comment_ids as $comment_id){
                    $comment = Comment::find($comment_id);
                    $comment->status = $action;
                    $comment->save();
                }
            }
            return redirect()->back()->with('success', 'Changes was saved');
        }
        $language = (isset($_GET['lang'])) ? $_GET['lang'] : '';
        $sLang = $language == '' ? '' : '_'.$language;
        $languages = Languages::get();

        $comments = Comment::where('comments.lang', $sLang)
            ->leftJoin('listing', 'comments.listing_id', '=', 'listing.id')
            ->leftJoin('listing_type', 'listing.listing_type_id', '=', 'listing_type.id')
            ->select('comments.*', 'listing.name'.$sLang.' AS title', 'listing.url'.$sLang.' AS url', 'listing_type.url'.$sLang.' AS type_url')
            ->orderBy('comments.created_at', 'DESC')->get();

        return view('admin.settings.comments.comments')->with([
            'comments' => $comments,
            'languages' => $languages,
            'language' => $language,
        ]);
    }

    public function edit_one_status(){
        $comment_id = $_GET['comment_id'];
        $new_status = $_GET['new_status'];

        $comment = Comment::find($comment_id);
        $comment->status = $new_status;
        $comment->save();

        return 1;
    }

    public function reply_to_comment ($id, Request $request){

        if($request->isMethod('post')){
            $user = Auth::user();

            $comment = new Comment();
            $comment->name = $user->name;
            $comment->content = $request->input('content');
            $comment->email = $user->email;
            $comment->status = 'approved';
            $comment->parent_id = $request->input('parent');
            $comment->listing_id = $request->input('listing_id');
            $comment->lang = $request->input('lang');
            $comment->save();

            return redirect()->back()->with('success', 'The reply to comment saved successfully, new?');
        }

        $comment = Comment::find($id);


        return view('admin.settings.comments.reply_to_comment')->with([
            'comment' => $comment,
        ]);
    }

    public function edit_comment ($id, Request $request){

        if($request->isMethod('post')){

            $comment = Comment::find($request->input('comment_id'));
            $comment->name = $request->input('name');
            $comment->content = $request->input('content');
            $comment->email = $request->input('email');;
            $comment->status = 'approved';
            $comment->save();

            return redirect()->back()->with('success', 'The comment was edited successfully!');
        }

        $comment = Comment::find($id);


        return view('admin.settings.comments.edit_comment')->with([
            'comment' => $comment,
        ]);
    }
}