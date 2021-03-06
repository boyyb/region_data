<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 2016/11/23
 * Time: 14:18
 */
class MY_library{
    protected $btime = null;//查询开始时间
    protected $etime = null;//查询结束时间
    protected $date_start = null;//周月查询开始日期
    protected $date_end = null;//周月查询结束日期
    protected $date_str = null;
    protected $EnvNo = array();//环境编号(展厅/展柜/库房)
    protected $CI = null;
    protected $texture_no_zg = array();
    protected $texture_no_kf = array();
    protected $hall = array();
    protected $showcase = array();
    protected $storeroom = array();
    protected $env_names = array();
    protected $texture = null;
    protected $material = null;
    protected $areas = null;
    protected $museum_id = null;
    protected $day = false;
    protected $date = false;
    
    function __construct()
    {
        $this->CI = & get_instance();
        $this->CI->load->config("texture");
        $this->texture = config_item("texture");
        $this->material = config_item("material");
        $this->areas = array();
    }

    protected function calculate($param,$ty,$date,$arr_areano,$alerts_no,$p){
        $data = array(
            "env_type"=>$ty,
            "param"=>$param,
            "mid"=>$this->museum_id,
            "date"=>$date
        );
        $data["abnormal"] = array();//异常数据
        $data["wave_arr"] = array();//日波动超标数据

        $abnormal = 0;
        $range = $range_normal = $arr = $arr_normal =  array();
        $range_areano = $range_normal_areano = array();
        $area_no_normal = array();
        foreach ($arr_areano as $area_no => $value){
            $datas = array();
            foreach ($value as $k=>$v){
                if($v["data"] === "NaN"){
                    unset($arr_areano[$area_no][$k]);
                }else{
                    $datas[] = $arr[] = floatval($v["data"]);
                }

            }
            $range[] = $range_areano[$area_no][] = max($datas) - min($datas);
        }

        $average = sizeof($arr)?round(array_sum($arr)/sizeof($arr),2):0;
        $data["average"] = $average;
        $sum = 0;
        foreach ($arr as $k =>$v){
            $sum += pow($v - $average,2);
        }
        $standard = sizeof($arr)?sqrt($sum/sizeof($arr)):0;//标准差
        asort($arr);
        $arr = array_values($arr);
        if(sizeof($arr)%2 == 0){ //中位值
            $end = intval(sizeof($arr)/2);
            $flag = $arr[$end-1] + $arr[$end];
            $data["middle"] = round($flag/2,2);
        }else{
            $data["middle"] = $arr[intval((sizeof($arr)+1)/2)-1];
        }
        $data["standard"] = round($standard,2);
        $data["max"] = max($arr);
        $data["min"] = min($arr);

        foreach ($arr_areano as $area_no => $value){
            foreach ($value as $v) {
                $z = $data["standard"]?($v["data"] - $average) / $data["standard"]:0;
                if(abs($z) > 3){
                    $abnormal++; //异常值个数
                    $data["abnormal"][] = array(
                        "date"=>date("Y年n月j日",$v["time"]),
                        "mid"=>$this->museum_id,
                        "equip_no"=>$v["equip_id"],
                        "val"=>$v["data"],
                        "time"=>date("H:i:s",$v["time"]),
                    );
                }else{
                    $area_no_normal[$area_no][] = $v["data"];
                }
            }
        }

        foreach ($area_no_normal as $area_no => $value){
            $range_normal[] = $range_normal_areano[$area_no][] = max($value) - min($value);
        }
        $min_range = round(min($range),2);
        $max_range = round(max($range),2);
        $min_range_normal = round(min($range_normal),2);
        $max_range_normal = round(max($range_normal),2);
        $num = $num_normal = 0;
        if($p == "temperature"){
            if($min_range>=4){
                $num += pow(2,3);
            }
            if($max_range>=4){
                $num += pow(2,2);
            }
            if($min_range_normal>=4){
                $num += pow(2,1);
            }
            if($max_range_normal>=4){
                $num += pow(2,0);
            }
            if($this->day){
                foreach ($range_areano as $areano =>$value){
                    foreach ($value as $v){
                        if($v>=4){
                            $data["wave_arr"][] = array(
                                "date"=>$this->day,
                                "mid"=>$this->museum_id,
                                "type"=>0,
                                "val"=>$v,
                                "env_name"=>$this->env_names[$areano]
                            );
                        }
                    }
                }
                foreach ($range_normal_areano as $areano =>$value){
                    foreach ($value as $v){
                        if($v>=4){
                            $data["wave_arr"][] = array(
                                "date"=>$this->day,
                                "mid"=>$this->museum_id,
                                "type"=>1,
                                "val"=>$v,
                                "env_name"=>$this->env_names[$areano]
                            );
                        }
                    }
                }
            }
        }elseif ($p == "humidity"){
            if($min_range>=5){
                $num_normal += pow(2,3);
            }
            if($max_range>=5){
                $num_normal += pow(2,2);
            }
            if($min_range_normal>=5){
                $num_normal += pow(2,1);
            }
            if($max_range_normal>=5){
                $num_normal += pow(2,0);
            }
            if($this->day) {
                foreach ($range_areano as $areano => $value) {
                    foreach ($value as $v) {
                        if ($v >= 5) {
                            $data["wave_arr"][] = array(
                                "date" =>$this->day,
                                "mid" => $this->museum_id,
                                "type" => 0,
                                "val" => $v,
                                "env_name" => $this->env_names[$areano]
                            );
                        }
                    }
                }
                foreach ($range_normal_areano as $areano => $value) {
                    foreach ($value as $v) {
                        if ($v >= 5) {
                            $data["wave_arr"][] = array(
                                "date" =>$this->day,
                                "mid" => $this->museum_id,
                                "type" => 1,
                                "val" => $v,
                                "env_name" => $this->env_names[$areano]
                            );
                        }
                    }
                }
            }
        }
        if(($p == "temperature" || $p == "humidity") && $this->day){
            $data["wave"] = $min_range.",".$max_range.",".$min_range_normal.",".$max_range_normal;
            $data["wave_status"] = $num?$num:($num_normal?$num_normal:0);
        }
//        echo $param.":".$p.":".$this->day."<br>";
//        echo array_key_exists("wave",$data)?$data["wave"]."<br><br>":'<br><br>';
        $data["count_abnormal"] = $abnormal;
        $data["compliance"] = sizeof($arr)?round((sizeof($arr) - $alerts_no)/sizeof($arr),2):0;
        return $data;
    }


    //统计函数-计算标准差
    protected function getStandardDeviation($avg, $list)
    {
        $total_var = 0;
        foreach ($list as $lv){
            $total_var += pow( ($lv - $avg), 2 );
        }
        return sqrt( $total_var / (count($list) ) );
    }
    //统计函数-计算中位值
    protected function getMiddleValue($list){
        sort($list);//升序排序
        $num = count($list);
        if($num%2 == 0){
            $middleValue = ($list[$num/2]+$list[($num/2)-1])/2;
        }else{
            $middleValue = $list[floor($num/2)];
        }
        return $middleValue;
    }
    //统计函数-计算异常值
    protected function getAbnormalValue($list){
        $avg = array_sum($list)/count($list);
        $sd = $this->getStandardDeviation($avg,$list);
        foreach($list as $v){
            $Z = abs(($v-$avg)/$sd);
            if($Z>3) return true;
        }
        return false;
    }
    //生成周/月日期列表
    protected function _date_list($s,$e){
        $date = array();
        for ($i = strtotime($s); $i <= strtotime($e); $i += 86400) {
            $date[] = "D". date("Ymd", $i);
        }
        return $date;
    }
    //日期转换
    protected function date_conversion($date){
        if($this->date){ //指定日期查询
            switch($date){
                case "yesterday": //指定某天
                    $this->btime = strtotime($this->date."00:00:00");
                    $this->etime = strtotime($this->date."23:59:59");
                    $this->date_str = "D".date("Ymd",$this->btime);
                    break;
                case "week"://指定天的所属周
                    $timestamp=strtotime($this->date);
                    $w=strftime('%w',$timestamp)==0?7:strftime('%w',$timestamp);
                    $this->btime = $timestamp-($w-1)*86400; //周开始
                    $this->etime = $timestamp+(7-$w)*86400+86399;//周结束
                    $this->date_str = "W".date("YW",$this->btime);
                    break;
                case "month"://指定天的所属月
                    $firstday = date("Ym01 ",strtotime($this->date));
                    $this->btime = strtotime($firstday." 00:00:00"); //月开始
                    $this->etime = strtotime(date('Ymd', strtotime($firstday)) . ' +1 month -1 day')+86399; //月结束
                    $this->date_str = "M".date("Ym",$this->btime);
                    break;
            }
        }else{
            switch ($date){
                case "yesterday": //昨天
                    $this->btime = strtotime('-1 day 00:00:00');
                    $this->etime = strtotime('-1 day 23:59:59');
                    $this->date_str = "D".date("Ymd",$this->btime);
                    break;
                case "week": //本周
                    if(date("w") == 1){ //处理上周数据
                        $this->btime = mktime(0,0,0,date('m'),date('d')-date('w')-6,date('y'));
                        $this->etime = mktime(23,59,59,date('m'),date('d')-date('w'),date('y'));
                        $this->date_str = "W".date("YW",$this->etime);
                    }else{//本周
                        $this->btime = mktime(0,0,0,date("m"),date("d")-(date("w")==0?7:date("w"))+1,date("Y"));
                        $this->etime = strtotime('-1 day 23:59:59');
                        $this->date_str = "W".date("YW",$this->etime);
                    }
                    break;
                case "month": //本月
                    if(date("d") == "01"){ //处理上月数据
                        $this->btime = mktime(0,0,0,date('m')-1,1,date('y'));
                        $this->etime = mktime(23,59,59,date("m"),0,date("y"));
                        $this->date_str = "M".date("Ym",$this->etime);
                    }else{//本月
                        $this->btime = mktime(0,0,0,date('m'),1,date('y'));
                        $this->etime = strtotime('-1 day 23:59:59');
                        $this->date_str = "M".date("Ym");
                    }
                    break;
            }
        }
        $this->date_start = date("Ymd",$this->btime);
        $this->date_end = date("Ymd",$this->etime);

    }
    
}