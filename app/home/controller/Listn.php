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
use think\Request;
/**
 * 文章列表
*/
class Listn extends Base
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
	public function index() {
		$list_id=input('id');
		$page=input('page');
		$pagesize=5;
		$menu=Db::name('menu')->find(input('id'));
		if(empty($menu)){
			$this->error(lang('operation not valid'));
		}
		$tplname=$menu['menu_listtpl'];
		$tplname=$tplname?:'list';
		if($tplname=="photo_list") $pagesize=4;//相册格式
		$model=Db::name('model')->find($menu['menu_modelid']);
		if($model){
			//判断ajax模板是否存在
			if(is_file($this->yf_theme_path.'ajax_'.$tplname) && request()->isAjax()){
				$data=Db::name($model['model_name'])->where($model['model_cid'],$list_id)->order($model['model_order'])->paginate($pagesize,false,['page'=>$page]);
				$tplname=":ajax_".$tplname;
				$lists['page'] = $data->render();
				//替换成带ajax的class
				$page_html=$lists['page'];
				$page_html=preg_replace("(<a[^>]*page[=|/](\d+).+?>(.+?)<\/a>)","<a href='javascript:ajax_page($1);'>$2</a>",$page_html);
			}else{
				$data=Db::name($model['model_name'])->where($model['model_cid'],$list_id)->order($model['model_order'])->paginate($pagesize,false);
				$lists['page'] = $data->render();
				$page_html=$lists['page'];
			}
			$lists['news']=$data;
		}else{
			//news
			if(request()->isAjax()){
				$lists=get_news('cid:'.$list_id.';order:news_time desc',1,$pagesize,null,null,array(),$page);
				$tplname=":ajax_".$tplname;
			}else{
				$lists=get_news('cid:'.$list_id.';order:news_time desc',1,$pagesize);
			}
			//替换成带ajax的class
			$page_html=$lists['page'];
			$page_html=preg_replace("(<a[^>]*page[=|/](\d+).+?>(.+?)<\/a>)","<a href='javascript:ajax_page($1);'>$2</a>",$page_html);
		}
		$this->assign('menu',$menu);
		$this->assign('page_html',$page_html);
		$this->assign('lists',$lists);
		$this->assign('list_id', $list_id);
		return $this->view->fetch(":$tplname");
	}
    public function search()
    {
		$k = input("keyword");
		$page = input("post.page");
		$pagesize=5;
		if (empty($k)) {
			$this -> error(lang('keywords empty'));
		}
		if(request()->isAjax()){
 			if(empty($page)){
				$this->success(lang('success'),url('home/Listn/search',array('keyword'=>$k)));
			}else{
				$lists=get_news('order:news_time desc',1,$pagesize,'keyword',$k,array(),$page);
				//替换成带ajax的class
				$page_html=$lists['page'];
				$page_html=preg_replace("(<a[^>]*page[=|/](\d+).+?>(.+?)<\/a>)","<a href='javascript:ajax_page($1);'>$2</a>",$page_html);
				$this->assign('page_html',$page_html);
				$this->assign('lists',$lists);
				$this -> assign("keyword", $k);
				return $this->view->fetch(":ajax_list");				
			} 
		}else{
			$lists=get_news('order:news_time desc',1,$pagesize,'keyword',$k);
			//替换成带ajax的class
			$page_html=$lists['page'];
			$page_html=preg_replace("(<a[^>]*page[=|/](\d+).+?>(.+?)<\/a>)","<a href='javascript:ajax_page($1);'>$2</a>",$page_html);
			$this->assign('page_html',$page_html);
			$this->assign('lists',$lists);
			$this -> assign("keyword", $k);		
			return $this->view->fetch(':search');
		}
    }
    public function fellow(){
	    $id  = input('id');
	    $tpl = input('menu_listtpl');
        $used_id = Cookie::get('id','id');
        $use = Db::name('member_list')
            ->join('member_group','member_group_id = member_list_groupid')->where([
            'member_list_id'=>$used_id,
            'member_group_open'=>1,
        ])
            ->field('member_list_nickname,user_url,friends_id,fans_id,follow_id,n_id')
            ->find();
        $arr = Db::name('news')->where([
            'n_id'=>['in',$use['n_id']],
            'news_back'=>0,
            'news_open'=>1,
        ])->order('listorder','desc')
            ->field('news_pic_allurl,news_pic_allurl_name,n_id,news_title,news_titleshort,pag_id,news_time,news_flag,news_source,news_hits,fabulous')
            ->select();

        $att_id = Db::name('member_list a')->where([
            'member_list_id'=>$use['friends_id'],
        ])->field('n_id')->find();

        $att = Db::name('news')->where([
            'n_id'=>['in',$att_id['n_id']],
            'news_back'=>0,
            'news_open'=>1,
        ])->order('listorder','desc')
           ->field('news_pic_allurl,news_pic_allurl_name,n_id,news_title,news_titleshort,pag_id,news_time,news_flag,news_source,news_hits,fabulous')
            ->select();

        foreach ($arr as $k=>$v){
            $arr[$k]['pag_id'] = Db::name('pag')->where(['id'=>['in',$arr[$k]['pag_id']]])->select();
        }
        foreach ($att as $k=>$v){
            $att[$k]['pag_id'] = Db::name('pag')->where(['id'=>['in',$att[$k]['pag_id']]])->select();
        }
            $att_zi = count($att);
            $arr_zi = count($arr);
            if($att_zi > $arr_zi){
                foreach ($att as $k=>$v){
                    if($k !=$att_zi-1){
                        $use['article'][$k]=$v;
                    }else{
                        $use['article'][$k]=$v;
                        foreach ($arr as $d=>$h){
                            $d++;
                            $use['article'][$k+$d]= $h;
                        }
                    }
                }
            }else{
                foreach ($arr as $k=>$v){
                    if($k !=$arr_zi-1){
                        $use['article'][$k]=$v;
                    }else{
                        $use['article'][$k]=$v;
                        foreach ($att as $d=>$h){
                            $d++;
                            $use['article'][$k+$d]= $h;
                        }
                    }

                }
            }
        if(!empty($use['article'])){

            foreach ( $use['article'] as $k=>$v){
                if($v['news_pic_allurl'] != null){
                    $use['article'][$k]['news_pic_allurl'] = explode(',',$v['news_pic_allurl']);
                    $use['article'][$k]['news_pic_allurl_shu'] =count($use['article'][$k]['news_pic_allurl']);
                    $use['article'][$k]['news_pic_allurl'] = reset( $use['article'][$k]['news_pic_allurl']);
                    $use['article'][$k]['news_pic_allurl_name'] = explode(',',$v['news_pic_allurl_name']);
                    $use['article'][$k]['news_pic_allurl_name_shu'] =count($use['article'][$k]['news_pic_allurl_name']);
                    $use['article'][$k]['news_pic_allurl_name'] = reset( $use['article'][$k]['news_pic_allurl_name']);
                }else{
                    $use['article'][$k]['news_pic_allurl'] ="";
                    $use['article'][$k]['news_pic_allurl_shu'] =0;
                    $use['article'][$k]['news_pic_allurl_name'] ="";
                    $use['article'][$k]['news_pic_allurl_name_shu'] =0;
                }
            }
          }

        if($use['fans_id'] == null){
            $use['fans_id'] = 0;
        }else{
            $use['fans_id'] = explode(',',$use['fans_id']);
            $use['fans_id'] = count($use['fans_id']);
        }
        if($use['follow_id'] == null){
            $use['follow_id'] = 0;
        }else{
            $use['follow_id'] = explode(',',$use['follow_id']);
            $use['follow_id'] = count($use['follow_id']);
        }
        if($use['n_id'] == null){
            $use['news_id'] = 0;
        }else{
            $use['news_id'] = explode(',',$use['n_id']);
            $use['news_id'] = count($use['news_id']);
        }

        $this->assign('id',$used_id);
        $this->assign('use',$use);
	    return $this->view->fetch(":$tpl");

    }
    public function fabulous(){
        $id = input('post.');
//        echo "<pre>";
//        print_r($id);
//        exit;
        $existence = Db::name('news')->where([
            'n_id'=>$id['n_id'],
            'news_open'=>1,
            'news_back'=>0
        ])->field('n_id,fabulous_id,fabulous')
            ->find();
        if($existence['fabulous_id'] != null){
          $fabulous = strstr($existence['fabulous_id'],$id['id']);
            if($fabulous == null){
                $fabulous_id = explode(',',$existence['fabulous_id']);
                $fabulous_zhang = count($fabulous_id);
                $fabulous_id[$fabulous_zhang] = $id['id'];
                $fabulous_id = implode(',',$fabulous_id);
                Db::name('news')->where([
                    'n_id'=>$id['n_id'],
                    'news_open'=>1,
                    'news_back'=>0
                ])->update(['fabulous_id'=>$fabulous_id,'fabulous'=>$existence['fabulous']+1]);
                return $existence['fabulous']+1;
            }else{
                return false;
            }
        }else{
            Db::name('news')->where([
                    'n_id'=>$id['n_id'],
                    'news_open'=>1,
                    'news_back'=>0
                ])->update(['fabulous_id'=>$id['id'],'fabulous'=>$existence['fabulous']+1]);
            return $existence['fabulous']+1;
        }
    }
    public function fabulous_piao(){
        $id = input('post.');

        $existence = Db::name('news')->where([
            'n_id'=>$id['n_id'],
            'news_open'=>1,
            'news_back'=>0
        ])->field('n_id,fabulous_id,fabulous')
            ->find();
        if($existence['fabulous_id'] != null){
            $fabulous = strstr($existence['fabulous_id'],$id['id']);
            if($fabulous == null){
                $fabulous_id = explode(',',$existence['fabulous_id']);
                $fabulous_zhang = count($fabulous_id);
                $fabulous_id[$fabulous_zhang] = $id['id'];
                $fabulous_id = implode(',',$fabulous_id);
                Db::name('news')->where([
                    'n_id'=>$id['n_id'],
                    'news_open'=>1,
                    'news_back'=>0
                ])->update(['fabulous_id'=>$fabulous_id,'fabulous'=>$existence['fabulous']+1]);
                return $existence['fabulous']+1;
            }else{
                return false;
            }
        }else{
            Db::name('news')->where([
                'n_id'=>$id['n_id'],
                'news_open'=>1,
                'news_back'=>0
            ])->update(['fabulous_id'=>$id['id'],'fabulous'=>$existence['fabulous']+1]);
            return $existence['fabulous']+1;
        }
    }
    public function substance(){
        $id = input('id');
        Db::name('news')->where(['n_id'=>$id])->setInc('news_hits');
        $used_id = Cookie::get('id', 'id');
        $this->assign('e_id',$id);
        $this->assign('n_id', $used_id);
       $content = Db::name('news')->where([
           'n_id'=>$id,
           'news_open'=>1,
           'news_back'=>0
       ])->find();
       if($content['news_content'] ==null){

           $content['news_pic_allurl'] = explode(',',$content['news_pic_allurl']);
           $subscriber = Db::name('member_list')->join('member_group','member_group_id = member_list_groupid')->where([
               'n_id'=>['like','%'.$content['n_id'].'%'],
           ])->field('member_list_id,member_list_nickname,user_url,n_id,member_group_name')->find();
           $content['author']['follow'] =  Db::name('member_list')->where([
               'member_list_id' => $used_id
           ])->field('follow_id')
               ->find();
           $other = Db::name('news')->where(['n_id'=>['in',$subscriber['n_id']],'news_back'=>0,'news_open'=>1])->limit(4)->field('n_id,news_title,news_img')->select();
            $this->assign('other',$other);
           if($content['author']['follow']['follow_id'] != null){
               $content['author']['follow'] = $content['author']['follow']['follow_id'];
           }else{
               $content['author']['follow'] ="";
           }

           if (strpos("".$content['author']['follow']."","".$subscriber['member_list_id']."") !== false) {
               $content['author']['follow_id'] = 1;
           }else{
               $content['author']['follow_id'] = 2;
           }
           $fabulous_id = explode(',',$content['fabulous_id']);
           if(in_array($used_id,$fabulous_id)){
               $fabulous = 1;
           }else{
               $fabulous = 0;
           }
           if($content['collection'] != null){
                $content['collection'] = explode(',',$content['collection']);
                if(in_array($used_id,$content['collection'])){
                    $content['if_content'] = 1;
                }else{
                    $content['if_content'] = 0;
                }
           }else{
               $content['if_content'] = 0;
           }
           $nominate = $this->nominate('1','news');

           $this->assign('nominate',$nominate);
           $this->assign('fabulous',$fabulous);
           $this->assign('content', $content);
           $this->assign('subscriber',$subscriber);
           $this->assign('content',$content);
           return $this->view->fetch(":opus");
       }else {
           $content['pag_id'] = explode(',', $content['pag_id']);
           foreach ($content['pag_id'] as $k => $v) {
               if ($v == null) {
                   unset($content['pag_id'][$k]);
               }
           }
           if ($content['news_flag'] != null) {
               $content['news_flag'] = Db::name('diyflag')->where(['diyflag_value' => $content['news_flag']])->field('diyflag_name')->find();
           }
           $content['author'] = Db::name('member_list')->where([
               'n_id' => ['like', '%' . $content['n_id'] . '%']
           ])->field('member_list_id,member_list_nickname,member_list_headpic,user_url')
               ->find();

           $content['author']['follow'] =  Db::name('member_list')->where([
               'member_list_id' => $used_id
           ])->field('follow_id')
               ->find();
           if($content['author']['follow']['follow_id'] != null){
               $content['author']['follow'] = $content['author']['follow']['follow_id'];
           }else{
               $content['author']['follow'] ="";
           }
//           echo "<pre>";
//           print_r($content);
//           exit;
           if (strpos("".$content['author']['follow']."","".$content['author']['member_list_id']."") !== false) {
               $content['author']['follow_id'] = 1;
           }else{
               $content['author']['follow_id'] = 2;
           }
//           $publish = Db::name('comments')->where([
//               'uid'=>$id,
//               'to_uid'=>'',
//           ])->order('createtime','desc')->select();
           $publish = Db::query("SELECT * FROM p_comments WHERE uid=$id AND ISNULL(to_uid) ORDER BY createtime DESC ");
           $zipublisgh = Db::query("SELECT * FROM p_comments WHERE uid=$id AND to_uid!='' ORDER BY createtime DESC ");
           foreach ($publish as $k=>$v){
               foreach ($zipublisgh as $item=>$value){
                   if($v['c_id'] == $value['to_uid']){
                       $publish[$k]['to_uid'][$item] = $value;
                  }
               }
           }

           $publish_nuber = count($publish);
           $tui = Db::name('news')->where('n_id','not in',function ($qurey) use ($used_id){
               $qurey->table('p_member_list')->where(['member_list_id'=>$used_id])->field('n_id');
           },'exists')->field('n_id,news_title,news_img')->select();
           $tui_nuber = array_rand($tui,4);
           foreach ($tui_nuber as $k=>$v){
               $tuijian[$k] = $tui[$v];
           }
           $this->assign('tui_nuber',$tui_nuber);
            $this->assign('publish_nuber',$publish_nuber);

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
//                      echo "<pre>";
//           print_r($publish);
//           exit;
           $this->assign('publish',$publish);
           $this->assign('content', $content);
           return $this->view->fetch(':bowen');
       }
    }
    public function nominate($num,$table,$where=[])
        {
            $pk = Db::name($table)->getPK();//获取主键

            $countcus = Db::name($table)->where($where)->field($pk)->select();//查询数据
            $con = '';
            $qu = '';
            foreach($countcus as $v=>$val){
                $con.= $val[$pk].'|';
            }
            $array = explode("|",$con);
            $countnum = count($array)-1;
            for($i = 0;$i <= $num;$i++){
                $sunum = mt_rand(0,$countnum);
                $qu.= $array[$sunum].',';
            }
            $list = Db::name($table)->where($pk,'in',$qu)
                ->field('n_id,news_title,news_img')
                ->find();
            if($list != array()){
                        $list['xia'] = Db::name('member_list')->where([
                            'n_id'=>['in',$list['n_id']],
                            'member_list_open'=>1,
                        ])->field('member_list_id,member_list_nickname')->find();
                        if(   $list['xia'] == null){
                            unset(  $list['xia']);
                        }
            }
            return $list;
    }
    public function follow(){
        $follow = input('post.');
        $id = Cookie::get('id', 'id');
        if($follow['tai'] == 2){
            $member = Db::name('member_list')->where([
                'member_list_id'=>$id,
            ])->field('follow_id')
                ->find();
            if($member['follow_id'] == null){
               $correct = Db::name('member_list')->where([
                    'member_list_id'=>$id,
                ])->update(['follow_id'=>$follow['id']]);
               if($correct ==true){
                   Db::name('member_list')->where([
                       'member_list_id'=>$follow['id']
                   ])->setInc('fans_id');
                   return true;
               }else{
                   return false;
               }
            }else{
                $arr = explode(',',$member['follow_id']);
                foreach ($arr as $k=>$v){
                    if($v == $follow['id']){
                        return false;
                    }
                }
                $arr_number = count($arr);
                $arr[$arr_number] = $follow['id'];
                $arr = implode(',',$arr);
                $correct = Db::name('member_list')->where([
                    'member_list_id'=>$id,
                ])->update(['follow_id'=>$arr]);
                if($correct ==true){
                    Db::name('member_list')->where([
                        'member_list_id'=>$follow['id']
                    ])->setInc('fans_id');
                    return true;
                }else{
                    return false;
                }
            }
        }else{
            $member = Db::name('member_list')->where([
                'member_list_id'=>$id,
            ])->field('follow_id')
                ->find();
            if($member != null){
                $member_array = explode(',',$member['follow_id']);
                foreach ($member_array as $k=>$v){
                    if($v == $follow['id']){
                        unset($member_array[$k]);
                    }
                }
                $member_array = implode(',',$member_array);
                $correct = Db::name('member_list')->where([
                    'member_list_id'=>$id,
                ])->update(['follow_id'=>$member_array]);
                if($correct ==true){
                    Db::name('member_list')->where([
                        'member_list_id'=>$follow['id']
                    ])->setDec('fans_id');
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }

        }
    }
    public function enshrine(){
        $enshrine = input("post.");
        $id = Cookie::get('id', 'id');
        $house = Db::name('news')->where([
            'n_id'=>$enshrine['n_id'],
            'news_back'=>0,
            'news_open'=>1,
        ])->field('collection')
            ->find();
        if($enshrine['tai'] == 0){
            if($house['collection'] == null){
                $succeed = Db::name('news')->where(['n_id'=>$enshrine['n_id']])->update(['collection'=>$id]);
                if($succeed == true){
                    return true;
                }else{
                    return false;
                }
            }else{
                $arr= explode(',',  $house['collection']);
              if(in_array($id,$arr)){
                  return false;
              }else {
                  $nuber = count($arr);
                  $arr[$nuber] = $id;
                  $arr = implode(',', $arr);
                  $succeedone = Db::name('news')->where(['n_id' => $enshrine['n_id']])->update(['collection' => $arr]);
                  if ($succeedone == true) {
                      return true;
                  } else {
                      return false;
                  }
              }
            }
        }else{
            $arr = explode(',',$house['collection']);
            foreach ($arr as $k=>$v){
                if($v == $id){
                    unset($arr[$k]);
                }
            };
            $arr = implode(',',$arr);
            $succeedtwo = Db::name('news')->where(['n_id'=> $enshrine['n_id']])->update(['collection' => $arr]);
            if ($succeedtwo == true) {
                return true;
            } else {
                return false;
            }
        }

    }
    public function affiliate(){
        $affiliate = input('post.');
        $id =  Cookie::get('id','id');
        // $arr = Db::name('member_list')->where(['member_list_id'=>$id])->field('area_id')->find();
        if($id != null){
            if($affiliate['member_list_email'] !=null && preg_match("/\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/",$affiliate['member_list_email'])){
                if($affiliate['member_list_tel'] != null && preg_match('/13[123569]{1}\d{8}|15[1235689]\d{8}|188\d{8}/',$affiliate['member_list_tel'])){
                    $interval = Db::name('member_list')->where(['member_list_id'=>$id])->field('area_id,area_time')->find();
                    if($interval['area_id'] == null){
                        $affiliate['area_time'] = time();
                        $preservation = Db::name('member_list')->where(['member_list_id'=>$id])->update($affiliate);
                        if($preservation == true){
                            $this->success('加入成功');
                        }else{
                            $this->error('失败，请稍后再试');
                        }
                    }else{
                        $time = $this->timediff($interval['area_time'],time());
                        if($time['day'] >= 30){
                            $preservation = Db::name('member_list')->where(['member_list_id'=>$id])->update($affiliate);
                            if($preservation == true){
                                $this->success('加入成功');
                            }else{
                                $this->error('失败，请稍后再试');
                            }
                        }else{
                            $station = Db::name('menu')->where(['id'=>$interval['area_id']])->field('menu_name')->find();
                            $this->error('您加入'.$station['menu_name'].'时间未满30天，无法切换站点');
                        }
                    }
                }else{
                    $this->error('电话为空或者格式不正确');
                }
            }else{
                $this->error('邮箱为空或者格式不正确');
            }
        }else{
            $this->error('请登录');
        }
    }
}
