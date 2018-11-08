<?php
// +----------------------------------------------------------------------
// | YFCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2016 http://www.rainfer.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: rainfer <81818832@qq.com>
// +----------------------------------------------------------------------
namespace app\home\controller;

use think\Db;
use think\Cookie;
use think\Session;
class News extends Base
{
    //文章内页
    public function index()
    {
		$page=input('page',1);
		$news=Db::name('news')->alias("a")->join(config('database.prefix').'member_list b','a.news_auto =b.member_list_id')->where(array('n_id'=>input('id'),'news_open'=>1,'news_back'=>0))->find();
		if(empty($news)){
		    $this->error(lang('operation not valid'));
		}
		$news_data=explode('_ueditor_page_break_tag_',$news['news_content']);
		$total=count($news_data);
		$news['content']=$news_data[$page-1];
		$news['page']='';
		if($total>1){
			$prevbtn=($page<=1)?'<li class="disabled"><span>&laquo;</span></li>':'<li><a href="' . url('home/News/index',['id'=>input('id'),'page'=>($page-1)]) . '">&laquo;</a></li>';
			$nextbtn=($page>=$total)?'<li class="disabled"><span>&raquo;</span></li>':'<li><a href="' . url('home/News/index',['id'=>input('id'),'page'=>($page+1)]) . '">&raquo;</a></li>';
			$link=$this->getLinks($page,$total,input('id'));
			$news['page']=sprintf(
				'<ul class="pagination">%s %s %s</ul>',
				$prevbtn,
				$link,
				$nextbtn
			);
		}
		$menu=Db::name('menu')->find($news['news_columnid']);
		if(empty($menu)){
		    $this->error(lang('operation not valid'));
		}
		$tplname=$menu['menu_newstpl'];
    	$tplname=$tplname?$tplname:'news';
		//自行根据网站需要考虑，是否需要判断
		$can_do=check_user_action('news'.input('id'),0,false,60);
		if($can_do){
			//更新点击数
			Db::name('news')->update(array("n_id"=>input('id'),"news_hits"=>array("exp","news_hits+1")));
			$news['news_hits']+=1;
		}
		$next=Db::name('news')->where(array("news_time"=>array("egt",$news['news_time']), "n_id"=>array('neq',input('id')),"news_open"=>1,'news_back'=>0,'news_columnid'=>$news['news_columnid']))->order("news_time asc")->find();
		$prev=Db::name('news')->where(array("news_time"=>array("elt",$news['news_time']), "n_id"=>array('neq',input('id')),"news_open"=>1,'news_back'=>0,'news_columnid'=>$news['news_columnid']))->order("news_time desc")->find();
		$t_open=config('comment.t_open');
        if($t_open){
            //获取评论数据
            $comments=Db::name('comments')->alias("a")->join(config('database.prefix').'member_list b','a.uid =b.member_list_id')->where(array("a.t_name"=>'news',"a.t_id"=>input('id'),"a.c_status"=>1))->order("a.createtime ASC")->select();
            $count=count($comments);
            $new_comments=array();
            $parent_comments=array();
            if(!empty($comments)){
                foreach ($comments as $m){
                    if($m['parentid']==0){
                        $new_comments[$m['c_id']]=$m;
                    }else{
                        $path=explode("-", $m['path']);
                        $new_comments[$path[1]]['children'][]=$m;
                    }
                    $parent_comments[$m['c_id']]=$m;
                }
            }
            $this->assign("count",$count);
            $this->assign("comments",$new_comments);
            $this->assign("parent_comments",$parent_comments);
        }
        $this->assign("t_open",$t_open);
		$this->assign($news);
		$this->assign('menu',$menu);
		$this->assign("next",$next);
    	$this->assign("prev",$prev);
		return $this->view->fetch(":$tplname");
    }
	//分页中间部分链接
	protected function getLinks($page,$total,$id)
	{
		$block = [
			'first'  => null,
			'slider' => null,
			'last'   => null
		];

		$side   = 3;
		$window = $side * 2;

		if ($total < $window + 6) {
			$block['first'] = $this->getUrlRange(1, $total,$id);
		} elseif ($page <= $window) {
			$block['first'] = $this->getUrlRange(1, $window + 2,$id);
			$block['last']  = $this->getUrlRange($total - 1, $total,$id);
		} elseif ($page > ($total - $window)) {
			$block['first'] = $this->getUrlRange(1, 2,$id);
			$block['last']  = $this->getUrlRange($total - ($window + 2), $total,$id);
		} else {
			$block['first']  = $this->getUrlRange(1, 2,$id);
			$block['slider'] = $this->getUrlRange($page - $side, $page + $side,$id);
			$block['last']   = $this->getUrlRange($total - 1, $total,$id);
		}
		$html = '';
		if (is_array($block['first'])) {
			$html .= $this->getUrlLinks($block['first'],$page);
		}
		if (is_array($block['slider'])) {
			$html .= '<li class="disabled"><span>...</span></li>';
			$html .= $this->getUrlLinks($block['slider'],$page);
		}
		if (is_array($block['last'])) {
			$html .= '<li class="disabled"><span>...</span></li>';
			$html .= $this->getUrlLinks($block['last'],$page);
		}
		return $html;
	}
	protected function getUrlLinks(array $urls,$page)
	{
		$html = '';
		foreach ($urls as $text => $url) {
			$html .=($text == $page)?'<li class="active"><span>' . $text . '</span></li>':'<li><a href="' . htmlentities($url) . '">' . $text . '</a></li>';
		}
		return $html;
	}
	protected function getUrlRange($start, $end,$id)
	{
		$urls = [];
		for ($page = $start; $page <= $end; $page++) {
			$urls[$page] = url('home/News/index',['id'=>$id,'page'=>$page]);
		}
		return $urls;
	}
    public function dolike()
    {
	    $this->check_login();
    	$id=input('id',0,'intval');
    	$can_like=check_user_action('news'.$id,1);
    	if($can_like){
			Db::name("news")->where('n_id',$id)->setInc('news_like');;
    		$this->success(lang('dolike success'));
    	}else{
    		$this->error(lang('dolike already'));
    	}
    }
    public function dofavorite()
    {
        $this->check_login();
		$key=input('key');
		if($key){
			$id=input('id');
			if($key==encrypt_password('news-'.$id,'news')){
				$uid=session('hid');
				$favorites_model=Db::name("favorites");
				$find_favorite=$favorites_model->where(array('t_name'=>'news','t_id'=>$id,'uid'=>$uid))->find();
				if($find_favorite){
					$this->error(lang('favorited already'));
				}else {
                    $data=array(
                        'uid'=>$uid,
                        't_name'=>'news',
                        't_id'=>$id,
                        'createtime'=>time(),
                    );
					$result=$favorites_model->insert($data);
					if($result){
						$this->success(lang('favorite success'));
					}else {
						$this->error(lang('favorite failed'));
					}
				}
			}else{
				$this->error(lang('favorite failed'));
			}
		}else{
			$this->error(lang('favorite failed'));
		}
	}
	public function district(){
        $id = input('id');
        $uid =  Cookie::get('id','id');
        $distroct = Db::name('menu')->where([
            'id'=>$id,
            'menu_open'=>1,
        ])->find();
        if($distroct["stationmaster_id"] == null){
            $stationmaster = "";
        }else {
            $stationmaster = Db::query("SELECT member_list_id,member_list_nickname,user_url FROM p_member_list WHERE member_list_id=" . $distroct["stationmaster_id"]);
        }
        if($distroct['groom_id'] == null){
            $photographer = "";
        }else{
            $photographer = Db::query("SELECT member_list_id,member_list_nickname,user_url FROM p_member_list WHERE member_list_id IN (".$distroct["groom_id"].")");
        }

        if($uid != null){
            $use = Db::query("SELECT * FROM p_member_list WHERE member_list_id=$uid");
            foreach($use as $k => $v) {
                $distroct['area_id'] = $v['area_id'];
            }
        }else{
            $distroct['area_id'] ="";
        }
        $broadcasting = Db::query( "SELECT plug_adtype_id,plug_adtype_name,plug_ad_pic,plug_ad_url,plug_ad_name FROM p_plug_adtype JOIN p_plug_ad ON plug_adtype_id = plug_ad_adtypeid  WHERE plug_adtype_id=4");
        $counter = Db::query("SELECT * FROM p_assortment");
        $member = count(explode(',',Db::query("SELECT groom_id FROM p_menu WHERE id=".$id)[0]['groom_id']));
        $ar = Db::query("SELECT groom_id FROM p_menu WHERE id=".$id);
        foreach ($ar as $k=>$v){
            $writing[$k]= Db::query("SELECT n_id FROM p_member_list WHERE member_list_id IN (".$v['groom_id'].")");
        }
        foreach ($writing as $k=>$v){
            foreach ($v as $item=>$value){
                $writing[$item] = count(explode(',',$value['n_id']));
            }
        }
        $writing = array_sum($writing);
        $this->assign('writing',$writing);
        $this->assign('member',$member);
        $this->assign('counter',$counter);
        $this->assign('photographer',$photographer);
        $this->assign('stationmaster',$stationmaster);
        $this->assign('broadcasting',$broadcasting);
        $this->assign('distroct',$distroct);
        $this->assign('id',$id);
        return $this->view->fetch(':district');
    }

    public function timediff($begin_time,$end_time)
    {
        if($begin_time < $end_time){
            $starttime = $begin_time;
            $endtime = $end_time;
        }
        else{
            $starttime = $end_time;
            $endtime = $begin_time;
        }
        $timediff = $endtime-$starttime;
        $days = intval($timediff/86400);
        $remain = $timediff%86400;
        $hours = intval($remain/3600);
        $remain = $remain%3600;
        $mins = intval($remain/60);
        $secs = $remain%60;
        $res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
        return $res;
    }
    public function login() {
        if(isset($_SESSION['ADMIN_ID'])){//已经登录
            $this->success(L('LOGIN_SUCCESS'),U("Index/index"));
        }else{
            if(empty($_SESSION['adminlogin'])){
                redirect(__ROOT__."/");
                $this->display(":login");
            }else{
                $this->display(":login");

            }

            }
        }
        //热门接口
        public function popular(){
            $id = input('id')?input('id'):null;
            $number = input('number')?input('number'):0;

            $numbertwo = input('numberTwo')?input('numberTwo'):20;

            if($id == null){return false;}
            $popular = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  WHERE production_id=$id AND news_open=1 AND news_back=0 ORDER BY news_hits LIMIT ".$number.",".$numbertwo);
            foreach ($popular as $k=>$v){
                $popular[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $popular;
        }
        //站长推荐接口
        public function recommends(){
            $id = input('id')?input('id'):null;
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            if($id == null){return false;}
            $recommends = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  WHERE production_id=$id AND news_open=1 AND news_back=0 AND nominate=1 ORDER BY news_hits LIMIT ".$number.",".$numbertwo);
            foreach ($recommends as $k=>$v){
                $recommends[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $recommends;
        }
        //最新
        public function newest(){
            $id = input('id')?input('id'):null;
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            if($id == null){return false;}
            $newest = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  WHERE production_id=$id AND news_open=1 AND news_back=0 AND nominate=1 ORDER BY news_time LIMIT ".$number.",".$numbertwo);
            foreach ($newest as $k=>$v){
                $newest[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $newest;
        }
        //类别
        public function category(){
            $id = input('id')?input('id'):null;
            $a_id = input('a_id')?input('a_id'):null;
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            if($id == null){return false;}
            if($a_id == null){
                $newest = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  WHERE production_id=$id AND news_open=1 AND news_back=0  ORDER BY news_time LIMIT ".$number.",".$numbertwo);
                foreach ($newest as $k=>$v){
                    $newest[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
                }
                return $newest;
            }else{
                $newest = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  WHERE production_id=$id AND assortment_id=$a_id AND news_open=1 AND news_back=0  ORDER BY news_time LIMIT ".$number.",".$numbertwo);
                foreach ($newest as $k=>$v){
                    $newest[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
                }
                return $newest;
            }
        }
        //站长
        public function stationmaster(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $stationmaster = Db::query("SELECT id,user_url,member_list_nickname,menu_name,member_list_province,birthday_url FROM p_menu JOIN p_member_list ON stationmaster_id=member_list_id WHERE stationmaster_id!='' LIMIT ".$number.",".$numbertwo);
            return $stationmaster;
        }
        //图片广场
        public function picture(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $picture = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits,collection FROM p_news WHERE news_scontent ='' LIMIT ".$number.",".$numbertwo);
            foreach ($picture as $k=>$v){
                $picture[$k]['collection'] = count(explode(',',$v['collection']));
                $picture[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $picture[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $picture;
        }
        //视觉广场
        public function vision(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $picture = Db::query("SELECT * FROM p_news WHERE news_scontent !='' LIMIT ".$number.",".$numbertwo);
            foreach ($picture as $k=>$v){
                $picture[$k]['collection'] = count(explode(',',$v['collection']));
                $picture[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $picture[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $picture;
        }
        //最新
        public function newests(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $newests = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news ORDER BY listorder DESC LIMIT ".$number.",".$numbertwo);
            foreach ($newests as $k=>$v){
                $newests[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $newests[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $newests;
        }
        //站长推荐
        public function station(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $station = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits,collection FROM p_news WHERE nominate=1 ORDER BY listorder DESC LIMIT ".$number.",".$numbertwo);
            foreach ($station as $k=>$v){
                $station[$k]['collection'] = count(explode(',',$v['collection']));
                $station[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $station[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $station;
        }
        //编辑推荐
        public function bluepencil(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $bluepencil = Db::name('news')->where([
                'index'=>1,
            ])->field("news_img,news_pic_allurl,n_id,fabulous,news_hits")->order('listorder','desc')->limit($number,$numbertwo)->select();
            foreach ($bluepencil as $k=>$v){
               // $bluepencil[$k]['collection'] = count(explode(',',$v['collection']));
                $bluepencil[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $bluepencil[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $bluepencil;
        }
        //人气
        public function popularity(){
            $number = input('number')?input('number'):0;
            $numbertwo = input('numberTwo')?input('numberTwo'):20;
            $popularity = Db::query("SELECT news_img,news_pic_allurl,n_id,fabulous,news_hits FROM p_news  ORDER BY news_hits DESC LIMIT ".$number.",".$numbertwo);
            foreach ($popularity as $k=>$v){
                $popularity[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
                $popularity[$k]['news'] = Db::query("SELECT member_list_nickname,member_list_id,user_url FROM p_member_list WHERE n_id=".$v['n_id']);
            }
            return $popularity;
        }
        //搜索
        public function search(){
            $id = input('id')?input('id'):null;
            $searchCont =  input('searchCont')?input('searchCont'):null;
           $a = Db::query("SELECT n_id FROM p_member_list WHERE member_list_id=".$id);
           foreach ($a as $k=>$v){
               $search = Db::query("SELECT n_id,news_img,news_pic_allurl,news_time,news_hits,news_title,fabulous,comment_count FROM p_news WHERE n_id IN (".$v['n_id'].") AND news_title LIKE CONCAT('%','$searchCont','%')");
           }
           foreach ($search as $k=>$v){
               $search[$k]['news_pic_allurl'] = count(explode(',',$v['news_pic_allurl']));
               $search[$k]['news_time'] = date('m',$v['news_time']).'-'.date('d',$v['news_time']);
           }
           return $search;
        }
    }
