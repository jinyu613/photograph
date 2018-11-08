<?php

namespace app\admin\controller;

use think\Controller;

//    use think\Request;
use think\Request;
use think\Session;
use think\Cookie;
class Upload extends Controller{

    public function Index(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');

        if($file){
            $info = $file->move(ROOT_PATH .'data'.DIRECTORY_SEPARATOR . 'upload');
            if($info){
                $url = "/data/upload/".$info->getSaveName();
                $url = str_replace("\\","/",$url);
                $arr = explode("\\",$info->getSaveName());
                $shuzi = count($arr);
                $name = $arr[$shuzi-1];
                $wenjian =$info->getInfo();
                $name1 = $wenjian['name'];

                $data = [
                    "name1"=>$name1,
                    "code" =>0,
                    "msg"  =>'',
                    "data" => ['src'=>$url],
                    "name"=>$name
                ];
                echo json_encode($data);
            }else{
                // 上传失败获取错误信息
                $data = [
                    "code" =>0,
                    "msg"  =>$file->getError(),
                    "data" => '',
                ];
                echo json_encode($data);
            }
        }
    }
}