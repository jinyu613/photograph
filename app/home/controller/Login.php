<?php
// +----------------------------------------------------------------------
// | YFCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2016 http://www.rainfer.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: rainfer <81818832@qq.com>
// +----------------------------------------------------------------------
namespace app\home\controller;

use think\Controller;
use think\Db;
use think\captcha\Captcha;
use think\log\driver\Test;
use think\Validate;
use think\Cookie;
use think\Session;
use think\Request;

class Login extends Base
{

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $id = Cookie::get('id','id');
        if($id == null){
            $this->redirect('index/entry');
            return;
        }
    }

    public function index()
    {
	    if(session('hid')){
			if($this->user['user_status']){
				$this->redirect(__ROOT__."/");
			}else{
				return $this->view->fetch('user:active');
			}
	    }else{
			return $this->view->fetch('user:login');
	    }
	}
	//验证码
	public function verify()
    {
        if (session('hid')) {
            $this->redirect(__ROOT__."/");
        }
		ob_end_clean();
		$verify = new Captcha (config('verify'));
		return $verify->entry('hid');
    }
	/*
     * 退出登录
     */
	public function logout()
    {
		session('hid',null);
		session('user',null);
		cookie('yf_logged_user',null);
		$this->redirect(__ROOT__."/");
	}
	
    //登录验证
    public function runlogin()
    {
		$member_list_username=input('member_list_username');
		$member_list_pwd=input('member_list_pwd');
		$remember=input('remember',0,'intval');
		$verify =new Captcha ();
		if (!$verify->check(input('verify'), 'hid')) {
			$this->error(lang('verifiy incorrect'));
		}
		$rule = [
			['member_list_username','require','{%username empty}'],
			['member_list_pwd','require','{%pwd empty}'],
		];
		$validate = new Validate($rule);
		$rst   = $validate->check(array('member_list_username'=>$member_list_username,'member_list_pwd'=>$member_list_pwd));
		if(true !==$rst){
			$this->error(join('|',$validate->getError()));
		}
		if(strpos($member_list_username,"@")>0){//邮箱登陆
            $where['member_list_email']=$member_list_username;
        }else{
            $where['member_list_username']=$member_list_username;
        }
		$member=Db::name("member_list")->where($where)->find();
		if (!$member||encrypt_password($member_list_pwd,$member['member_list_salt'])!==$member['member_list_pwd']){
				$this->error(lang('username or pwd incorrect'));
		}else{
			if($member['member_list_open']==0){
				$this->error(lang('user disabled'));
			}
			//更新字段
			$data = array(
				'last_login_time' => time(),
				'last_login_ip' => request()->ip(),
			);
			Db::name("member_list")->where(array('member_list_id'=>$member["member_list_id"]))->update($data);
			session('hid',$member['member_list_id']);
			session('user',$member);
			if($remember && $member['user_status']){
				//更新cookie
				cookie('yf_logged_user', jiami("{$member['member_list_id']}.{$data['last_login_time']}"));
			}
			
			//根据需要决定是否同步后台登录状态
			$admin=Db::name('admin')->where('member_id',$member['member_list_id'])->find();
			if($admin){
                // 记录登录
                $auth = array(
                    'aid'             			 => $admin['admin_id'],
                    'admin_avatar'    			 => $admin['admin_avatar'],
                    'admin_last_change_pwd_time' => $admin['admin_changepwd'],
                    'admin_realname'       		 => $admin['admin_realname'],
                    'admin_username'          	 => $admin['admin_username'],
                    'member_id'        			 => $admin['member_id'],
                    'admin_last_ip' 			 => $admin['admin_last_ip'],
                    'admin_last_time'   		 => $admin['admin_last_time']
                );
                session('admin_auth', $auth);
                session('admin_auth_sign', data_signature($auth));
			}
			
			$this->success(lang('login success'),url('home/Login/check_active'));
		}
    }
    public function forgot_pwd()
    {
		return $this->view->fetch('user:forgot_pwd');
	}
	public function writings(){
        $classify = Db::name('assortment')->select();
        $diyflag = Db::name('diyflag')->select();
        $this->assign('diyflag',$diyflag);
        $this->assign('classify',$classify);
        return $this->view->fetch(':writings');
    }
	//验证码
	public function verify_forgot()
    {
        if (session('hid')) {
            $this->redirect(__ROOT__."/");
        }
		ob_end_clean();
		$verify = new Captcha (config('verify'));
		return $verify->entry('forgot');
    }
    public function runforgot_pwd()
    {
		if(request()->isPost()){
			$member_list_email=input('member_list_email');
			$member_list_username=input('member_list_username');
			$verify =new Captcha ();
			if (!$verify->check(input('verify'), 'forgot')) {
				$this->error(lang('verifiy incorrect'));
			}
			$rule = [
				['member_list_email','require|email','{%email empty}|{%email format incorrect}'],
				['member_list_username','require','{%username empty}'],
			];
			$validate = new Validate($rule);
			$rst   = $validate->check(array('member_list_email'=>$member_list_email,'member_list_username'=>$member_list_username));
			if(true !==$rst){
				$this->error(join('|',$validate->getError()));
			}
			$find_user=Db::name("member_list")->where(array("member_list_username"=>$member_list_username))->find();
			if($find_user){
				if(empty($find_user['member_list_email'])){
					//先更新字段邮箱
					Db::name("member_list")->where(array("member_list_username"=>$member_list_username))->setField('member_list_email',$member_list_email);
					$find_user['member_list_email']=$member_list_email;
				}
				if($find_user['member_list_email']==$member_list_email){
					//发送重置密码邮件
					$activekey=md5($find_user['member_list_id'].time().uniqid());//激活码
					$result=Db::name("member_list")->where(array("member_list_id"=>$find_user['member_list_id']))->update(array("user_activation_key"=>$activekey));
					if(!$result){
						$this->error(lang('activation code generation failed'));
					}
					//生成重置链接
					$url = url('home/Login/pwd_reset',array("hash"=>$activekey), "", true);
					$template = lang('emal text').
								<<<hello
								<a href="http://#link#">http://#link#</a>
hello;
					$content = str_replace(array('http://#link#','#username#'), array($url,$member_list_username),$template);
					$send_result=sendMail($member_list_email, 'YFCMF '.lang('pwd reset'), $content);
					if($send_result['error']){
						$this->error(lang('send pwd reset email failed'));
					}else{
						$this->success(lang('send pwd reset email success'),url('home/Index/index'));
					}
				}else{
					$this->error(lang('email not the same as registered email'));
				}
			}else {
				$this->error(lang('member not exist'));
			}
		}
	}
    public function pwd_reset()
    {
	    $hash=input("get.hash");
	    $find_user=Db::name("member_list")->where(array("user_activation_key"=>$hash))->find();
	    if (empty($find_user)){
	        $this->error(lang('pwd reset hash incorrect'),url('home/Index/index'));
	    }else{
			$this->assign("hash",$hash);
			return $this->view->fetch('user:pwd_reset');
	    }
	}
	//验证码
	public function verify_reset()
    {
        if (session('hid')) {
            $this->redirect(__ROOT__."/");
        }
		ob_end_clean();
		$verify = new Captcha (config('verify'));
		return $verify->entry('pwd_reset');
    }
    public function runpwd_reset()
    {
		if(request()->isPost()){
			$verify =new Captcha();
			if (!$verify->check(input('verify'), 'pwd_reset')) {
				$this->error(lang('verifiy incorrect'));
			}
			$rule = [
				['password','require|length:5,20','{%pwd empty}|{%pwd length}'],
				['repassword','require|confirm:password','{%repassword empty}|{%repassword incorrect}'],
				['hash','require','{%pwd reset hash empty}'],
			];
			$validate = new Validate($rule);
			$rst= $validate->check(array('password'=>input('password'),'hash'=>input('hash'),'repassword'=>input('repassword')));
			if(true !==$rst){
				$this->error(join('|',$validate->getError()));
			}else{
				$password=input('password');
				$hash=input('hash');
				$member_list_salt=random(10);
				$member_list_pwd=encrypt_password($password,$member_list_salt);
				$result=Db::name("member_list")->where(array("user_activation_key"=>$hash))->update(array('member_list_pwd'=>$member_list_pwd,'user_activation_key'=>'','member_list_salt'=>$member_list_salt));
				if($result){
					$this->success(lang('pwd reset success'),url("home/Login/index"));
				}else {
					$this->error(lang('pwd reset failed'));
				}
			}
		}
	}
    public function check_active()
    {
		$this->check_login();
		if($this->user['user_status']){
			$this->redirect(__ROOT__."/");
		}else{
			//判断是否激活
			return $this->view->fetch('user:active');
		}
	}
	//重发激活邮件
    public function resend()
    {
		$this->check_login();
		$current_user=$this->user;
		if($current_user['user_status']==0){
			if($current_user['member_list_email']){
				$active_options=get_active_options();
				$activekey=md5($current_user['member_list_id'].time().uniqid());//激活码
				$result=Db::name('member_list')->where(array("member_list_id"=>$current_user['member_list_id']))->update(array("user_activation_key"=>$activekey));
				if(!$result){
					$this->error(lang('activation code generation failed'));
				}
				//生成激活链接
				$url = url('home/Register/active',array("hash"=>$activekey), "", true);
				$template = $active_options['email_tpl'];
				$content = str_replace(array('http://#link#','#username#'), array($url,$current_user['member_list_username']),$template);
				$send_result=sendMail($current_user['member_list_email'], $active_options['email_title'], $content);
				if($send_result['error']){
					$this->error(lang('send active email failed'));
				}else{
					$this->success(lang('send active email success'),url('home/Login/index'));
				}
			}else{
				$this->error(lang('no registered email'),url('home/Login/index'));
			}
		}else{
		    $this->error(lang('activated'),url('home/Index/index'));
		}
	}
    public function personal(){
        $id = Cookie::get('id','id');

        $explicit = Db::name('member_list')->where([
            'member_list_id'=>$id,
        ])
            ->field('birthday_url,member_list_id,member_list_nickname,member_list_province,member_list_city,member_list_sex,member_list_city,member_list_headpic,member_list_province,user_url')
            ->find();

        $this->assign('explicit',$explicit);
        return $this->view->fetch(':gerenshezhi');
    }
    public function preservation(Request $request){
            $preservation = $request->param();
//            echo "<pre>";
//            print_r($preservation);
//            exit;
            $id = $preservation['member_list_id'];
            unset($preservation['member_list_id']);
            $err =  Db::name('member_list')->where([
                'member_list_id'=>$id,
            ])->update($preservation);
            if($err == true){
                $this->success('ok','login/personal');
            }else{
                $this->error('no','login/personal');
            }
    }
    public function release(){
        $classify = Db::name('assortment')->select();
        $diyflag = Db::name('diyflag')->select();
        $this->assign('diyflag',$diyflag);
        $this->assign('classify',$classify);
        return $this->view->fetch(':works');
    }
    public function label_name(){
        $name = input('name');
        $label = Db::name('pag')->where([
            'pag_name'=>$name,
        ])->find();
        if($label == null){
            $label_id = Db::name('pag')->insertGetId(['pag_name'=>$name]);
            $label = Db::name('pag')->where([
                'id'=>$label_id,
            ])->find();
        }
        return $label;
    }
    public function announce(Request $request){
        $announce = $request->param();

        if(!empty($announce['news_pic_allurl'])){
            $announce['news_pic_allurl'] = implode(',',$announce['news_pic_allurl']);
        }else{
            $announce['news_pic_allurl'] = "";
        }
        if(!empty($announce['news_pic_allurl_name'])){
            $announce['news_pic_allurl_name'] = implode(',',$announce['news_pic_allurl_name']);
        }else{
            $announce['news_pic_allurl_name'] = "";
        }

        if(!empty($announce['pag_id'])){
            $announce['pag_id'] = implode(',',$announce['pag_id']);
        }else{
            $announce['pag_id'] = "";
        }

//        $diyflag = Db::name('diyflag')->where('diyflag_id',$announce['copyright'])->field('diyflag_value')->find();
//        if($diyflag['diyflag_value'] !=null){
//            $announce['news_flag'] = $diyflag['diyflag_value'];
//        }
        $announce['news_auto'] = 1;
        $announce['news_time'] = time();
        $uid = Cookie::get('id','id');
        $area = Db::query("SELECT area_id FROM p_member_list WHERE member_list_id=$uid");
        foreach ($area as $k=>$v){
            $announce['production_id'] =$v['area_id'];
        }
       $suss = Db::name('news')->insertGetId($announce);
      if($suss == true) {
          $new_list = Db::name('news')->where([
              'n_id'=>$suss,
          ])->field('n_id')
              ->find();

          $list_id =  Cookie::get('id','id');
          $list = Db::name('member_list')->where([
              'member_list_id'=>$list_id
          ])->field('n_id')->find();

          if($list['n_id'] == null){
              $list = Db::name('member_list')->where([
                  'member_list_id'=>$list_id
                  ])->update(['n_id'=>$new_list['n_id']]);
              if($list == true){
                  $this->success();
              }else{
                  $this->error();
              }
          }else{
              $nid = explode(",",$list['n_id']);
              $zhang = count($nid);
              foreach ($nid as $k => $v) {
                  if($k == $zhang-1){
                      $nid[$k + 1] = $new_list['n_id'];
                  }
              }

              $nid = implode(',',$nid);

              $list = Db::name('member_list')->where([
                  'member_list_id'=>$list_id
              ])->update(['n_id'=>$nid]);
              if($list == true){
                  $this->success();
              }else{
                  $this->error();
              }
          }
      }else{
          $this->$this->error();
      }
    }
    public function bowen(){
        $bowen = input('post.');
//        echo "<pre>";
//        print_r($bowen);
//        exit;
        if(empty($bowen['news_pic_allurl_name'])){
            $this->error('GG');
        }
        $bowen['news_pic_allurl_name'] = implode(',',$bowen['news_pic_allurl_name']);
        if(empty($bowen['news_pic_allurl'])){
            $this->error('GG');
        }
        $bowen['news_pic_allurl'] = implode(',',$bowen['news_pic_allurl']);
        if($bowen['news_time'] != null){
            $bowen['news_time'] = strtotime($bowen['news_time']);
        }else{
            $bowen['news_time'] = "";
        }
        $bowen['news_auto'] = 1;
        $suss = Db::name('news')->insertGetId($bowen);
        if($suss == true) {
            $new_list = Db::name('news')->where([
                'n_id' => $suss,
            ])->field('n_id')
                ->find();

            $list_id = Cookie::get('id', 'id');

            $list = Db::name('member_list')->where([
                'member_list_id' => $list_id
            ])->field('n_id')->find();
            if ($list['n_id'] == null) {
                $list = Db::name('member_list')->where([
                    'member_list_id' => $list_id
                ])->update(['n_id' => $new_list['n_id']]);
                if ($list == true) {
                    $this->success();
                } else {
                    $this->error();
                }
            } else {
                $nid = explode(",", $list['n_id']);
                $zhang = count($nid);
                foreach ($nid as $k => $v) {
                    if($k == $zhang-1){
                        $nid[$k + 1] = $new_list['n_id'];
                    }
                }
                $nid = implode(',', $nid);
                $list = Db::name('member_list')->where([
                    'member_list_id' => $list_id
                ])->update(['n_id' => $nid]);
                if ($list == true) {
                    $this->success();
                } else {
                    $this->error();
                }
            }
        }
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
    public function year(){
        $year = input("post.");
        $list = Db::name('member_list')->where([
            'member_list_id'=>$year['id'],
        ])->find();
        $list['n_id'] = Db::name('news')->where([
            'n_id'=>['in',$list['n_id']],
            'news_open'=>1,
            'news_back'=>0,
        ]) ->field('news_pic_allurl,news_pic_allurl_name,news_time,n_id,news_title,pag_id,news_hits,fabulous')
            ->order('listorder','desc')
            ->select();
        foreach ( $list['n_id'] as $k=>$v){
            if($year['year'] != null){
                if(date('Y',$list['n_id'][$k]['news_time']) != $year['year']){
                    unset($list['n_id'][$k]);
                }else{
                    $list['n_id'][$k]['pag_id'] = Db::name('pag')->where([
                        'id'=>['in',$v['pag_id']]
                    ])->select();
                }
            }else{
                $list['n_id'][$k]['pag_id'] = Db::name('pag')->where([
                    'id'=>['in',$v['pag_id']]
                ])->select();
            }
        }
        foreach ( $list['n_id'] as $k=>$v){
            if($v['news_pic_allurl'] != null){
                $list['n_id'][$k]['news_pic_allurl'] = explode(',',$v['news_pic_allurl']);
                $list['n_id'][$k]['news_pic_allurl_shu'] =count($list['n_id'][$k]['news_pic_allurl']);
                $list['n_id'][$k]['news_pic_allurl'] = reset($list['n_id'][$k]['news_pic_allurl']);
                $list['n_id'][$k]['news_pic_allurl_name'] = explode(',',$v['news_pic_allurl_name']);
                $list['n_id'][$k]['news_pic_allurl_name_shu'] =count($list['n_id'][$k]['news_pic_allurl_name']);
                $list['n_id'][$k]['news_pic_allurl_name'] = reset($list['n_id'][$k]['news_pic_allurl_name']);
            }else{
                $list['n_id'][$k]['news_pic_allurl'] ="";
                $list['n_id'][$k]['news_pic_allurl_shu'] =0;
                $list['n_id'][$k]['news_pic_allurl_name'] ="";
                $list['n_id'][$k]['news_pic_allurl_name_shu'] =0;
            }
            $list['time'][$k] = date("Y",$list['n_id'][$k]['news_time']);

        }
        $time = $this->a_array_unique($list['time']);
        $list['zhang'] = count($list['n_id']);
        return $list;
    }

    public function message(){
        $message = input('post.');
        $message['plug_sug_name'] = $this->get_real_ip();
        $message['plug_sug_ip'] = $this->get_real_ip();
        $sucss = Db::name('plug_sug')->insert($message);
        if($sucss == true){
            $this->redirect('login/contact');
        }else{
            $this->error();
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
                if(!eregi ('^(10│172.16│192.168).', $ips[$i])){
                    $ip=$ips[$i];
                    break;
                }
            }
        }
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }
    public function notification(){
        $u_id = Cookie::get('id','id');
        $notification = Db::name('member_list')->where([
            'member_list_id'=>$u_id,
        ])->field('member_list_id,n_id')
            ->find();
        $arr = Db::name('news')->where([
            'n_id'=>['in',$notification['n_id']],
            'news_back'=>0,
            'news_open'=>1,
            'chaka'=>0,
        ])
            ->field('n_id,news_title,fabulous_id,zan_time,news_img')
            ->select();

       foreach ($arr as $k=>$v){
            if($v['fabulous_id'] == null){
                unset($arr[$k]);
            }else{
                $arr[$k]['zan_time'] = explode(',', $arr[$k]['zan_time']);
                $menu_list[$k]= Db::name('member_list')->where([
                     'member_list_id'=>['in',$v['fabulous_id']],
                     'member_list_open'=>1,
                 ])->field('member_list_id,member_list_nickname,user_url,follow_id')->select();
            }
        }
        if(!empty($menu_list)){
            foreach ($menu_list as $k=>$v){
                foreach ($v as $d=>$g){
                    $menu_list[$k][$d]['wenzhang'] = $arr[$k];
                    if($menu_list[$k][$d]['wenzhang']['zan_time'] == array()){
                        $menu_list[$k][$d]['wenzhang']['zan_time'] = $menu_list[$k][$d]['wenzhang']['zan_time'][$d];
                    }
             }
     
        }
        foreach($menu_list as $item=>$value){
            foreach($value as $k=>$v){
                $arr2[]=$v;
            }
        }
     $att = $this->reorder2($arr2);
    }else{
       $att ="";
    }


       
//        foreach ($att as $k=>$v){
//            $att[$k]['follow_id'] = Db::query("SELECT member_list_id,member_list_nickname,user_url FROM p_member_list WHERE member_list_id IN (".$v['follow_id'].")");
//        }
        $private = Db::query("SELECT member_list_id,follow_id FROM p_member_list WHERE member_list_id=".$u_id);
        foreach($private as $item=>$value){

                $private = Db::name('member_list')->where(['member_list_id'=>['in',$value['follow_id']]])->field("member_list_id,member_list_nickname,user_url")->select();
        }
        $afff = Db::query("SELECT n_id FROM p_member_list WHERE member_list_id=".$u_id);
        if($afff[0]['n_id'] !=null){

            $criticism_comment = Db::query("SELECT * FROM p_comments  WHERE ISNULL(to_uid) AND uid IN (".$afff[0]['n_id'].")");

            foreach ($criticism_comment as $k=>$v){
                if($v['uid'] != null){
                    $criticism_comment[$k]['uid'] = Db::query("SELECT n_id,news_title FROM p_news WHERE n_id=".$v['uid']);
                    foreach ($criticism_comment[$k]['uid'] as $item=>$value){
//                        if($value ==null){
//                            unset($criticism_comment[$k]);
//                        }
                        $criticism_comment[$k]['uid'] = $value;
                    }
                }
                $criticism_comment[$k]['xia'] = count($criticism_comment);
//                    $criticism_comment[$k]['xia'] = count(Db::query("SELECT * FROM p_comments WHERE to_uid=".$v['c_id']));
                if($criticism_comment[$k]['uid'] == null){
                    unset($criticism_comment[$k]);
                }
            }
//            echo "<pre>";
//            print_r($criticism_comment);
//            exit;

        }else{
            $criticism_comment = array();
        }
    $this->assign('criticism_comment',$criticism_comment);
    $this->assign('private',$private);
    $this->assign('att',$att);
     return $this->view->fetch(":notification");
    }
    public function reorder2($arr){
        foreach ($arr as $k=>$v){
            foreach ($arr as $d=>$g){
                if(intval($v['wenzhang']['zan_time']) > intval($g['wenzhang']['zan_time'])){
                    $a = $arr[$k];
                    $arr[$k]= $arr[$d];
                    $arr[$d]=$a;
                }
            }
        }
       return $arr;
    }
}