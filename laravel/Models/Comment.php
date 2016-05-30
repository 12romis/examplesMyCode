<?php namespace App\Models;

use App\HelpersClasses\Tr\Tr;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model {


    public $readyComments = array();
    public $commentsFromDB = array();

	public function saveComment ($data){ //сохраняет новый комментарий в базу

        $this->name = $data['name'];
        $this->content = $data['content'];
        $this->email = $data['email'];
        $this->status = 'wait for approve';
        $this->parent_id = $data['parent_id'];
        $this->listing_id = $data['listing_id'];
        $this->lang = $data['lang'];
        $this->save();
    }

    public function getComments ($id) {

        $this->commentsFromDB = $this->where(['listing_id' => $id, 'lang' => Tr::getPMainLang(), 'status'=>'approved'])->get();
        foreach ($this->commentsFromDB as $item){ // формирование массива коментариев для вывода на блейде
            if ($item->parent_id == 0) {
                $level = 1;
                $item->level = $level;
                $this->readyComments[]=$item;

                $level++;
                $this->prepareComments($item->id, $level);
            }
        }
        return $this->readyComments;
    }

    public function prepareComments ($id, $level){ // просмотр ответов на коментарии и другие ответы
        foreach ($this->commentsFromDB as $item){
            if ($item->parent_id==$id){
                $item->level = $level;
                $this->readyComments[]=$item;
                $level++;
                $this->prepareComments($item->id, $level);
                $level--;
            }
        }



    }

}
