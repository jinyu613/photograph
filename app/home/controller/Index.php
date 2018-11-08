<?php
// +----------------------------------------------------------------------
// | YFCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2016 http://www.rainfer.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: rainfer <81818832@qq.com>
// +----------------------------------------------------------------------
namespace app\home\controller;

use think\Cache;
use think\Db;
use think\captcha\Captcha;
use think\Cookie;
use think\Session;
use think\Request;

class Index extends Base
{
	public function index(Request $request)
    {
        $user_id = Cookie::get('id','id');
        $home = Db::name('news')->where([
            'index'=>1,
            'news_open'=>1,
            'news_back'=>0,
        ])->field('n_id,news_title,fabulous,news_img,fabulous,fabulous_id')->select();
        foreach ($home as $k=>$v){
            $home[$k]['fabulous_id'] = explode(',',$v['fabulous_id']);
            if($user_id != null){
                if(in_array($user_id,$home[$k]['fabulous_id'])){
                    $home[$k]['fabulous_id'] = 1;
                }else{
                    $home[$k]['fabulous_id'] = 2;
                }
            }else{
                $home[$k]['fabulous_id'] = 2;
            }

            $home[$k]['xia'] = Db::name('member_list')->where([
                'n_id'=>['like','%'.$v['n_id'].'%']
            ])
                ->field('member_list_id,user_url,member_list_nickname')
                ->find();
        }
        $jiqiao = Db::query("SELECT n_id,news_title,news_scontent,news_img FROM p_menu JOIN p_news ON id=news_columnid WHERE parentid=20 ORDER BY p_news.listorder DESC LIMIT 4");
        $shijue = Db::query("SELECT n_id,news_title,news_scontent,news_img FROM p_news WHERE news_source!='' ORDER BY listorder DESC LIMIT 8");
        $remensheyin = Db::query("SELECT member_list_id,birthday_url,user_url,member_list_nickname,member_list_province FROM p_member_list WHERE member_list_open=1 AND user_status=1 LIMIT 4");
//        echo "<pre>";
//        print_r($home);
//        exit;
        $this->assign('remensheyin',$remensheyin);
        $this->assign('shijue',$shijue);
        $this->assign('jiqiao',$jiqiao);
        $this->assign('home',$home);
        return $this->view->fetch(':index');
	}
	public function visit()
    {
		$user=Db::name("member_list")->where(array("member_list_id"=>input('id',0,'intval')))->find();
		if(empty($user)){
			$this->error(lang('member not exist'));
		}
		$this->assign($user);
		return $this->view->fetch('user:index');
	}
	public function verify_msg()
	{
		ob_end_clean();
		$verify = new Captcha (config('verify'));
		return $verify->entry('msg');
	}
	public function lang()
	{
		if (!request()->isAjax()){
			$this->error(lang('submission mode incorrect'));
		}else{
			$lang=input('lang_s');
			switch ($lang) {
				case 'cn':
					cookie('think_var', 'zh-cn');
				break;
				case 'en':
					cookie('think_var', 'en-us');
				break;
				//其它语言
				default:
					cookie('think_var', 'zh-cn');
			}
			Cache::clear();
			$this->success(lang('success'),url('home/Index/index'));
		}
	}
	public function addmsg()
    {
		if (!request()->isAjax()){
			$this->error(lang('submission mode incorrect'));
		}else{
			$verify =new Captcha ();
			if (!$verify->check(input('verify'), 'msg')) {
				$this->error(lang('verifiy incorrect'));
			}
			$data=array(
				'plug_sug_name'=>input('plug_sug_name'),
				'plug_sug_email'=>input('plug_sug_email'),
				'plug_sug_content'=>input('plug_sug_content'),
				'plug_sug_addtime'=>time(),
				'plug_sug_open'=>0,
				'plug_sug_ip'=>request()->ip(),
			);
			$rst=Db::name('plug_sug')->insert($data);
			if($rst!==false){
				$this->success(lang('message success'));
			}else{
				$this->error(lang('message failed'));
			}
		}
	}
	public function zhuce(){
	    return $this->view->fetch(":zhuce");
    }
    public function yanzheng(){
        $parameter = input('photo');
        $existence = Db::name('member_list')->where(['member_list_username'=>$parameter])->find();
        if($existence == null){
                $apl = 'C68869089';
                $yanzheng = rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
                Session::set('name',$yanzheng,'think');
                return $yanzheng;
//            $key = '243d9abea8c12ab8be7bf00e403298ee';
//            $content = '您的验证码是：'.$yanzheng.'。请不要把验证码泄露给其他人。';
//            $url = "http://106.ihuyi.cn/webservice/sms.php?method=Submit&account=$apl&password=$key&mobile=$parameter&content=$content";
//            $success = file_get_contents($url);
//            if($success == true){
//                return true;
//            }else{
//                return false;
//            }

        }else{
            return false;
        }

    }
    public function get_real_ip(){
        $ip=false;
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ips=explode (', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
            if($ip){ array_unshift($ips, $ip); $ip=FALSE; }
            for ($i=0; $i < count($ips); $i++){
                if(!preg_match ('^(10│172.16│192.168).', $ips[$i])){
                    $ip=$ips[$i];
                    break;
                }
            }
        }
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }
    public function submission(Request $request){
        $parameter = $request->param();

        if($parameter != null){

             $yanzhen = Session::pull('name');

        if($parameter['yanzhenma'] = $yanzhen){
//            echo "<pre>";
//            print_r($yanzhen);
//            print_r($parameter);
//            exit;
            if(strlen($parameter['key'])> 8){
                $arr['member_list_pwd'] = md5($parameter['key']);
                $arr['member_list_username'] = $parameter['photo'];
                $arr['member_list_nickname'] = $parameter['photo'];
                $arr['member_list_addtime'] = time();
                $arr['last_login_ip'] = $this->get_real_ip();
                $arr['member_list_groupid']= 1;
                if($arr == true){
                    $succ = Db::name('member_list')->insertGetId($arr);
                    if($succ == true){
                        Cookie::init(['prefix'=>'think_','expire'=>3600,'path'=>'/']);
                        Cookie::set('name',$succ,['prefix'=>'think_','expire'=>7200]);
                        $this->success('','/');
                    }else{
                        $this->error();
                    }
                }else{
                    $this->error();
                }
            }else{
                $this->error();
            }
        }else{
            $this->error();
        }
        }else{
            $this->error();
        }
    }
    public function processing(Request $request){
        $processing = $request->param();
        if($processing['photo'] !=null){
            $photo = Db::name('member_list')->where([
                'member_list_username'=>$processing['photo'],
                'member_list_open'=>1,
            ])->field('member_list_id,member_list_username,member_list_pwd')
                ->find();
            if($photo == true){
                if($photo['member_list_pwd'] == md5($processing['key'])){
                    Cookie::init(['prefix'=>'think_','expire'=>7200,'path'=>'/']);
                    Cookie::set('id',$photo['member_list_id'],['prefix'=>'id','expire'=>7200]);
                    $this->redirect('index/centrality?id='.$photo['member_list_id']);
                }else{
                    $this->error();
                }
            }else{
                $this->error();
            }
        }else{
            $this->error();
        }
    }
    public function entry(){
        $id = Cookie::get('id','id');
        if($id == null){
            return $this->view->fetch(":entry");
        }else{
            $this->redirect('Login/centrality');
        }

    }
    public function gerenshezhi(){
        return $this->view->fetch(":gerenshezhi");
    }
    public function cancellation1(){
        Cookie::delete('id','id');
        return true;
    }
    //忘记密码
    public function forgot(){
        return $this->view->fetch(":forgot");
    }
    public function find_password(){
        $tel = input("tel");
        $id = Db::name('member_list')->where('member_list_username',$tel)->field("member_list_id")->find();
        Cookie::set('member_list_id',$id,7200);
        if($id){
            return 1;
        }else{
            return '账号错误！';
        }
    }
    public function find_password2(){
        $yanzheng = input("yanzheng");
        $id = Session::get('name','think');
        if($yanzheng == $id){
            return 1;
        }else{
            return '验证码错误！';
        }
    }
    public function xiugai(){
        $password = input("password");
        $password = md5($password);
        $id = Cookie::get('member_list_id');
        $res = Db::name('member_list')->where('member_list_id',$id['member_list_id'])->update(['member_list_pwd' => $password]);
        if($res){
            return true;
        }else{
            return false;
        }
    }
    public function submenu(){
        $tpl = input('menu_listtpl');
        $id = input('id');

        $xia = Db::name('menu')->where([
            'parentid'=>$id,
            'menu_open'=>1
        ])->field('id,menu_name,menu_listtpl')->select();
        foreach ($xia as $k=>$v){
            $xia[$k]['xia'] = Db::name('menu')->where([
                'parentid'=>$v['id'],
                'menu_open'=>1
            ])->order('listorder','desc')->field('id,menu_name,menu_listtpl,menu_newstpl')->select();
        }
        $remen = Db::name('menu')->where([
            'menu_remen'=>1,
            'menu_open'=>1,
        ])->limit(4)
           ->field('id,menu_name,menu_listtpl,menu_newstpl,menu_img')
            ->select();

        foreach ($remen as $k=>$v){
            $remen[$k]['member_list_id'] = Db::query("SELECT n_id FROM p_member_list WHERE area_id=".$v['id']);
            if( $remen[$k]['member_list_id'] == null){
                $remen[$k]['member_list_id'] =0;
            }else{
                foreach ( $remen[$k]['member_list_id'] as $o=>$d){
                    $a[$o]= $d['n_id'];
                    $remen[$k]['member_list_id'] = count(Db::name("news")->where([
                        'n_id'=>['in',$d['n_id']],
                    ])->field('n_id')->select());
                }
            }
//            echo "<pre>";
//            print_r($remen);
//            exit;
            $remen[$k]['nuber'] = count($remen[$k]['member_list_id']);
        }

        $assortment = Db::query("SELECT * FROM p_assortment");

        $this->assign('assortment',$assortment);
        $this->assign('remen',$remen);
        $this->assign('xia',$xia);
        $this->assign('idd',$id);
        if($id == 26){
            $id = 20;
            $this->assign('t_id',26);
        }else{
            $this->assign('t_id',null);
        }

        $broadcast = $this->picture(1);
        $thumbnails = $this->picture(2);
        $subgrade = Db::name('menu a')->join('menu b','a.id = b.parentid')->where([
            'a.id'=>$id,
            'b.menu_open'=>1,
        ])->field('b.id,b.menu_name,a.id as a_id,a.menu_listtpl')
            ->select();
        foreach ($subgrade as $k=>$v){
            $subgrade[$k]['xia'] = Db::name('news')->where([
                'news_columnid'=>$v['id'],
                'news_open'=>1,
                'news_back'=>0
            ])->field('n_id,news_title,news_hits,news_scontent,news_img')
                ->select();
        }

        $whole = Db::name('menu')->join('news','id = news_columnid')->where([
            'parentid'=>$id,
            'news_open'=>1,
            'news_back'=>0,
            'menu_open'=>1,
        ])->field('n_id,news_title,news_hits,news_scontent,news_img')
            ->select();
        $hotdoc = Db::name('menu b')->join('menu a','b.id = a.parentid')->join('news','a.id = news_columnid')->where([
            'b.id'=>$id,
        ])->order('news_hits','desc')->limit(6)->field('n_id,news_title')->select();
        $interview = Db::name('member_list')->where([
            'user_status'=>1,
        ])
            ->field('member_list_id,member_list_nickname,user_url')
            ->select();
        $inquiries = Db::name('member_list')->where([
            'user_status'=>1,
        ])->field('member_list_id,member_list_nickname,user_url')
            ->select();
        $campaign = $this->picture(3);
        $pag = Db::name('pag')->where(['pag_open'=>1])
            ->select();
        foreach ($pag as $k=>$v){

            $pag[$k]['news'] = Db::name('news')->where([
                'pag_id'=>['like','%'.$v['id'].'%']
            ])->count();
           $pag[$k]['p_pag_time'] = $this->timediff(time(),$pag[$k]['p_pag_time']);
           $pag[$k]['p_pag_time'] = '剩余'. $pag[$k]['p_pag_time']['day'].'天'.$pag[$k]['p_pag_time']['hour'].'小时';
        }

        $this->assign('pag',$pag);
        $this->assign('campaign',$campaign);
        $this->assign('inquiries',$inquiries);
        $this->assign('hotdoc',$hotdoc);
        $this->assign('whole',$whole);
        $this->assign('id',$id);
        $this->assign('subgrade',$subgrade);
        $this->assign('thumbnails',$thumbnails);
        $this->assign('broadcast',$broadcast);
        return $this->view->fetch(":$tpl");
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
    public function picture($id){
        return Db::name('plug_adtype')->join('plug_ad','plug_ad_adtypeid = plug_adtype_id')->where([
            'plug_adtype_id'=>$id,
            'plug_ad_open'=>1,
        ])->order('plug_ad_order','desc')
            ->field('plug_ad_id,plug_ad_name,plug_ad_pic,plug_ad_url')
            ->select();
    }
    public function contact(){
        $r_id = input('r_id');
        $contact = Db::name('menu')->where([
            'id'=>28,
            'menu_open'=>0,
        ])->field('id,menu_name,menu_listtpl')
            ->find();
        $contact['xia'] = Db::name('menu')->where([
            'parentid'=>$contact['id'],
            'menu_open'=>1,
        ])->field('id,menu_name,menu_content')
            ->order('listorder','desc')
            ->select();
        foreach ($contact['xia'] as $k=>$v){
            $contact['xia'][$k]['xia'] = Db::name('menu')->where([
                'parentid'=>$v['id'],
                'menu_open'=>1,
            ])->field('id,menu_name,menu_content')
                ->order('listorder','desc')
                ->select();
        }
        $this->assign('r_id',$r_id);
        $this->assign('contact',$contact);
        return $this->view->fetch(":contact");
    }
    public function substance(){
        $id = input('id');
        $used_id = Cookie::get('id', 'id');
        $news = Db::name('news')->where([
            'n_id'=>$id,
            'news_back'=>0,
            'news_open'=>1,
        ])->field('n_id,news_title,news_source,news_content,news_time,news_scontent,news_hits,news_titleshort,news_img,pag_id,collection')
            ->find();
        if($news['pag_id'] == null){
            $news['pag_id'] = array();
        }else{
            $news['pag_id'] = Db::name('pag')->where([
                'id'=>['in',$news['pag_id']],
            ])->select();
        }
        $news['collection'] = explode(',',$news['collection']);

        if(!empty($used_id)){
            if(in_array($used_id,$news['collection'])){
                $news['collection'] = 0;
            }else{
                $news['collection'] = 1;
            }
        }else{
            $news['collection'] = 0;
        }
        $extend = Db::name('news')->where([
            'n_id'=>['>',$id],
            'news_back'=>0,
            'news_open'=>1,
        ])->field('n_id,news_title,news_img')
            ->find();
      if($extend ==true){
         $this->assign('extend',$extend);
      }
        $publish = Db::query("SELECT * FROM p_comments WHERE uid=$id AND ISNULL(to_uid) ORDER BY createtime DESC ");
        $zipublisgh = Db::query("SELECT * FROM p_comments WHERE uid=$id AND to_uid!='' ORDER BY createtime DESC ");
        foreach ($publish as $k=>$v){
            foreach ($zipublisgh as $item=>$value){
                if($v['c_id'] == $value['to_uid']){
                    $publish[$k]['to_uid'][$item] = $value;
                }
            }
        }
        foreach ($publish as $k=>$v){
            $publish[$k]['t_id'] = Db::name('member_list')->join('member_group','member_list_groupid = member_group_id')->where([
                'member_list_id'=>$v['t_id'],
                'member_list_open'=>1
            ])->field('member_list_id,member_list_nickname,user_url,fans_id,follow_id,member_group_name,member_list_province')
                ->find();
            if( $publish[$k]['t_id']['follow_id'] == null){
                $publish[$k]['t_id']['follow_id'] = 0;
            }else{
                $publish[$k]['t_id']['follow_id'] = count(explode(',',$publish[$k]['t_id']['follow_id']));
            }
            if($v['to_uid'] !=null){
                foreach ($v['to_uid'] as $item=>$value){
                    $publish[$k]['to_uid'][$item]['t_id'] = Db::name('member_list')->join('member_group','member_list_groupid = member_group_id')->where([
                        'member_list_id'=>$value['t_id'],
                        'member_list_open'=>1
                    ])->field('member_list_id,member_list_nickname,user_url,fans_id,follow_id,member_group_name,member_list_province')->find();
                    if($publish[$k]['to_uid'][$item]['t_id']['follow_id'] == null){
                        $publish[$k]['to_uid'][$item]['t_id']['follow_id'] = 0;
                    }else{
                        $publish[$k]['to_uid'][$item]['t_id']['follow_id'] = count(explode(',',$publish[$k]['to_uid'][$item]['t_id']['follow_id']));
                    }
                }
            }
        }
//        echo "<pre>";
//        print_r($publish);
//        exit;
      $publish_nuber = count($publish);
      $this->assign('publish_nuber',$publish_nuber);
      $this->assign('publish',$publish);
      $this->assign('e_id',$id);

      $this->assign('n_id',$id);
        $this->assign('news',$news);
        return $this->view->fetch(":substance");
    }
    public function detailed(){
        $id = input('id');
        $used_id = Cookie::get('id', 'id');
        $news = Db::name('news')->where([
            'interview'=>$id,
            'news_back'=>0,
            'news_open'=>1,
        ])->order('news_time','desc')
            ->find();
        if($news['pag_id'] == null){
            $news['pag_id'] = array();
        }else{
            $news['pag_id'] = Db::name('pag')->where([
                'id'=>['in',$news['pag_id']],
            ])->select();
        }
        $news['collection'] = explode(',',$news['collection']);
        if(in_array($used_id,$news['collection'])){
            $news['collection'] = 0;
        }else{
            $news['collection'] = 1;
        }
        $extend = Db::name('news')->where([
            'n_id'=>['>',$id],
            'news_back'=>0,
            'news_open'=>1,
        ])->field('n_id,news_title,news_img')
            ->find();
        if($extend ==true){
            $this->assign('extend',$extend);
        }
        $publish = Db::name('comments')->where([
            'uid'=>$id,
        ])->order('createtime','desc')->select();
        foreach ($publish as $k=>$v){
            $publish[$k]['t_id'] = Db::name('member_list')->join('member_group','member_list_groupid = member_group_id')->where([
                'member_list_id'=>$v['t_id'],
                'member_list_open'=>1
            ])->field('member_list_id,member_list_nickname,user_url,fans_id,follow_id,member_group_name,member_list_province')
                ->find();
            if( $publish[$k]['t_id']['follow_id'] == null){
                $publish[$k]['t_id']['follow_id'] = 0;
            }else{
                $publish[$k]['t_id']['follow_id'] = count(explode(',',$publish[$k]['t_id']['follow_id']));
            }
        }
        $publish_nuber = count($publish);
        $this->assign('publish_nuber',$publish_nuber);
        $this->assign('publish',$publish);
        $this->assign('news',$news);
        $this->assign('e_id',$news['n_id']);
        return $this->view->fetch(":substance");
    }
    public function through(){
        $like = input('like');
        $used_id = Cookie::get('id', 'id');
        $news_like = Db::name('news')->where([
            'news_title'=>['like','%'.$like.'%'],
            'news_back'=>0,
            'news_open'=>1,
        ])->where(['news_content' => ['exp', 'is not null']])
            ->field('n_id,news_title,news_titleshort,news_img,news_time,news_pic_allurl,pag_id,news_content,news_hits')
            ->order('listorder','desc')->select();
        $news_null = $news_like;
        foreach ($news_null as $k=>$v){
            if($v['news_content'] == null){
                unset($news_null[$k]);
            }else{
                $news_null[$k]['pag_id'] = Db::name('pag')->where(['id'=>['in',$v['pag_id']]])->select();
                if($news_null[$k]['news_pic_allurl'] != null){
                    $news_null[$k]['news_pic_allurl'] = explode(',',$news_null[$k]['news_pic_allurl']);
                }else{
                    $news_null[$k]['news_pic_allurl'] = "";
                }
                $news_null[$k]['nuber'] = count($news_null);
                if($news_null[$k]['news_pic_allurl'] != null){
                    $news_null[$k]['news_pic_allurl'] = $news_null[$k]['news_pic_allurl'][count($news_null[$k]['news_pic_allurl'])-1];
                }else{
                    $news_null[$k]['news_pic_allurl'] = "";
                }
            }
        }
        foreach ($news_like as $k=>$v){
            if($v['news_content'] != null){
                unset($news_like[$k]);
            }else{
                $news_like[$k]['pag_id'] = Db::name('pag')->where(['id'=>['in',$v['pag_id']]])->select();
                if($news_like[$k]['news_pic_allurl'] != null){
                    $news_like[$k]['news_pic_allurl'] = explode(',',$news_like[$k]['news_pic_allurl']);
                }else{
                    $news_like[$k]['news_pic_allurl'] = "";
                }
                $news_like[$k]['nuber'] = count($news_like);
                $news_like[$k]['member_list'] = Db::name('member_list')->where([
                    'n_id'=>['like','%'.$v['n_id'].'%'],
                    'member_list_open'=>1,
                ])->field('member_list_id,member_list_nickname,user_url')->find();
            }
        }
        $atlas = count($news_like);
        $subscriber = Db::name('member_list')->where([
            'member_list_nickname'=>['like','%'.$like.'%'],
            'member_list_open'=>1,
        ])->field('member_list_id,member_list_nickname,user_url,follow_id')->select();
        if($used_id != null){
            $used = Db::name('member_list')->where([
                'member_list_id'=>$used_id,
                'member_list_open'=>1,
            ])->field('member_list_id,follow_id')
                ->find();
            $used = explode(',',$used['follow_id']);
            foreach ($subscriber as $k=>$v){
                if(in_array($v['member_list_id'],$used)){
                    $subscriber[$k]['follow_id'] = 1;
                }else{
                    $subscriber[$k]['follow_id'] = 2;
                }
            }
        }
        $this->assign('news_null',$news_null);
        $this->assign('id',$used_id);
        $this->assign('subscriber',$subscriber);
        $this->assign('atlas',$atlas);
        $this->assign('news_like',$news_like);
        return $this->view->fetch(':search_list');
    }
    public function centrality(){
        $id = input('id')?input('id'):null;
        if($id == null){
            $id = Cookie::get('id', 'id');
            if($id == null){
                $this->redirect('index/entry');
                return;
            }
        }
        $news = Db::name('member_list')->where([
            'member_list_id'=>$id,
        ])->find();
        $type = input('type')?input('type'):null;
        $news['n_id'] = Db::name('news')->where([
            'n_id'=>['in',$news['n_id']],
            'news_open'=>1,
            'news_back'=>0,
        ])
            ->field('news_pic_allurl,news_pic_allurl_name,news_time,n_id,news_title,pag_id,news_hits,fabulous,comment_count')->order('listorder','desc')
            ->select();
        $news['zhang'] = count($news['n_id']);
        if($type == null){
            if(!empty($news['follow_id'])){
                $news['follow_id'] = count(explode(',',$news['follow_id']));
            }else{
                $news['follow_id'] = 0;
            }
        }elseif($type == 2){
            $news['n_id'] = Db::query("SELECT * FROM p_news WHERE collection!='' AND collection LIKE CONCAT('%',$id,'%')");
        }else{
           $use = Db::query("SELECT  member_list_nickname,member_list_id,member_list_email,member_list_tel,member_group_name,member_list_province,last_login_ip FROM p_member_list JOIN p_member_group ON member_list_groupid=member_group_id WHERE member_list_id=".$id);
           foreach ($use as $k=>$v){
               $use=$v;
           }
            $this->assign('use',$use);
        }

        if($news['n_id'] != array()){
            foreach ( $news['n_id'] as $k=>$v){
                $news['n_id'][$k]['pag_id'] = Db::name('pag')->where([
                    'id'=>['in',$v['pag_id']]
                ])->select();
            }
            foreach ( $news['n_id'] as $k=>$v){
                if($v['news_pic_allurl'] != null){
                    $news['n_id'][$k]['news_pic_allurl'] = explode(',',$v['news_pic_allurl']);
                    $news['n_id'][$k]['news_pic_allurl_shu'] =count($news['n_id'][$k]['news_pic_allurl']);
                    $news['n_id'][$k]['news_pic_allurl'] = reset($news['n_id'][$k]['news_pic_allurl']);
                    $news['n_id'][$k]['news_pic_allurl_name'] = explode(',',$v['news_pic_allurl_name']);
                    $news['n_id'][$k]['news_pic_allurl_name_shu'] =count($news['n_id'][$k]['news_pic_allurl_name']);
                    $news['n_id'][$k]['news_pic_allurl_name'] = reset($news['n_id'][$k]['news_pic_allurl_name']);
                }else{
                    $news['n_id'][$k]['news_pic_allurl'] ="";
                    $news['n_id'][$k]['news_pic_allurl_shu'] =0;
                    $news['n_id'][$k]['news_pic_allurl_name'] ="";
                    $news['n_id'][$k]['news_pic_allurl_name_shu'] =0;
                }
                $news['time'][$k] = date("Y",$news['n_id'][$k]['news_time']);

            }
            $time = $this->a_array_unique($news['time']);

        }else{
            $time = array();
        }
//        echo "<pre>";
//        print_r($type);
//        exit;
        $this->assign('type',$type);
        $this->assign('time',$time);
        $this->assign('id',Cookie::get('id','id'));
        $this->assign('news',$news);
        return $this->view->fetch(':zhongxin');
    }
    public function a_array_unique($array){
        $out = array();
        foreach ($array as $key=>$value) {
            if (!in_array($value, $out)){
                $out[$key] = $value;
            }
        }
        $out = array_values($out);
        return $out;
    }
    public function criticism(){
        $id = input('id');
        $c_content = input('c_content');
        $n_id = input('n_id');
        $to_uid = input('to_uid');
        $used_id = Cookie::get('id', 'id');
        $name = Db::name('member_list')->where(['member_list_id'=>$id,'member_list_open'=>1])->field('member_list_nickname')->find();
        $arr = [
            't_id'=>$id,
            'to_uid'=>null,
            'c_content'=>$c_content,
            'createtime'=>time(),
            't_name'=>$name['member_list_nickname'],
            'uid'=>$n_id
        ];
        $arr = Db::name('comments')->insertGetId($arr);

        if($arr == true){
            $publish =  Db::name('member_list')->join('comments','t_id = member_list_id')->join('member_group','member_list_groupid = member_group_id')->where([
                'c_id'=>$arr,
            ])->find();
            $publish['to_uid'] = $arr;
                if( $publish['follow_id'] == null){
                    $publish['follow_id'] = 0;
                }else{
                  $publish['ppppp'] =  $publish['follow_id'];
                    $publish['follow_id'] = count(explode(',',$publish['follow_id']));
                }
                $publish['createtime'] = date("m",$publish['createtime']).'月'.date('d',$publish['createtime']).'日';
        if($used_id != $publish['member_list_id']){
                    $publish['ppppp'] = explode(',',$publish['ppppp']);
                    if(in_array($used_id,$publish['ppppp'])){
                        $publish['ppppp'] = 1;
                    }else{
                        $publish['ppppp'] = 0;
                    }
                }else{
                 $publish['ppppp'] = 0;
             }
            return $publish;
        }else{
            return false;
        }
    }
    public function restore(){
        $restore = input('post.');
        echo "<pre>";
        print_r($restore);
        exit;
    }
    public function summarizing(){
        $id = input('id');
        $xia = Db::name('menu a')->join('menu b','a.parentid = b.parentid')->where([
            'a.id'=>$id
        ])->field('b.id,b.menu_name,b.menu_listtpl')->select();
        $tpl = input('menu_listtpl');
        $campaign = $this->picture(3);
        $pag = Db::name('pag')->where(['pag_open'=>1])
            ->select();
        foreach ($pag as $k=>$v){

            $pag[$k]['news'] = Db::name('news')->where([
                'pag_id'=>['like','%'.$v['id'].'%']
            ])->count();
            $pag[$k]['p_pag_time'] = $this->timediff(time(),$pag[$k]['p_pag_time']);
            $pag[$k]['p_pag_time'] = '剩余'. $pag[$k]['p_pag_time']['day'].'天'.$pag[$k]['p_pag_time']['hour'].'小时';
        }
        $this->assign('pag',$pag);
        $this->assign('campaign',$campaign);
        $this->assign('xia',$xia);
        $this->assign('idd',$id);
        return $this->view->fetch(":$tpl");
    }
    public function summar(){
        $id = input("id");
        return Db::query("SELECT * FROM p_menu WHERE menu_open=1 AND parentid=".$id." ORDER BY listorder DESC");
//        $a = Db::name('menu')->where([
//            'parentid'=>$id,
//            'menu_open'=>1
//        ])->order('listorder','desc')
//            ->field('id,menu_name,menu_listtpl,menu_newstpl,parentid')
//            ->select();

    }
    public function huifu(){
        $huifu = input('post.');
//        $huifu['uid'] = $huifu['n_id'];
//        unset($huifu['n_id']);
        $arr = [
            't_id'=>$huifu['to_uid'],
            'uid'=>$huifu['n_id'],
            'to_uid'=>$huifu['c_id'],
            'c_content'=>$huifu['c_content'],
            'createtime'=>time()
        ];
        unset($huifu);
        $news = Db::query("SELECT member_list_nickname FROM p_member_list WHERE member_list_id=".$arr['t_id']);
        foreach ($news as $k=>$v){
            $arr['t_name'] = $v['member_list_nickname'];
        }
        $arr = Db::name('comments')->insertGetId($arr);
        if($arr == true){
            return Db::query("SELECT * FROM p_comments WHERE c_id=".$arr);
        }
    }
}