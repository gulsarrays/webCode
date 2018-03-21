<?php

namespace App\Repositories;

use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Price;
use DB;
use Carbon\Carbon;
use DateTime;
use DateInterval;
use DatePeriod;
use SoapBox\Formatter\Formatter;

class RatesRepository {

    private $totalNights;
    private $startDate;
    private $endDate;
    private $actualEndDate;
    private $exchangeRate;
    private $currencyCode;
    private $occupancyIds;
    private $noOfPassengers;
    private $quantityg;
    private $tsServiceId;
    private $ratesServiceId;
    private $serviceOptionStatus;
    private $nNightsChargingPolicy;
    private $nNightsChargingPolicyError;
    private $service_type_id;

    public function __construct(Service $service, ExchangeRateRepository $exchangeRateRepository) {
        $this->service = $service;
        $this->exchangeRateRepository = $exchangeRateRepository;
    }

    public function getServiceByTsId($serviceTsId) {
        return Service::where('ts_id', $serviceTsId)->first();
        //return Service::with('currency')->where('ts_id', $serviceTsId)->first();
    }

    public function getServiceRate($serviceOptionId, $startDate, $endDate) {
        return DB::select("select buy_price,sell_price,season_period_id,start,end from prices join season_periods on (prices.season_period_id=season_periods.id) where priceable_id=? AND season_period_id IN (select id from season_periods where start<=? AND end>=? OR start<=? AND end>=?)", [$serviceOptionId, $startDate, $startDate, $endDate, $endDate]);
    }

    public function serviceOptionsAndRates($checkforError=array()) {

        $serviceOptionsAndRates = $this->serviceOptionsAndRatesWithPriceBrand();


        if (count($serviceOptionsAndRates) == 0) { // if Record doesn't exits with respect to price band

            $serviceOptionsAndRates = $this->serviceOptionsAndRatesWithOutPriceBrand();

        }

        if(empty($serviceOptionsAndRates) && !empty($checkforError)) {
            if(in_array('contractPeriod',$checkforError)) {
                $serviceOptionsAndRates = $this->serviceOptionsAndRatesWitoutStartAndEndDate();
                $serviceOptionsAndRates['Error'] = 'ContractPeriodError';
            }
        }

        return $serviceOptionsAndRates;
    }

    private function serviceOptionsAndRatesWithPriceBrand() {


        $str_status_check = '  AND prices.status='.$this->serviceOptionStatus.' and service_options.status= '.$this->serviceOptionStatus.' ';
        $str_status_check1 = '  AND prices.status='.$this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';
            $str_status_check1 = '';
        }

        //$priceObjdata = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from season_periods, prices join service_options on (prices.priceable_id = service_options.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id AND price_bands.min<=? AND price_bands.max>=?) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) AND occ.id IN ($this->occupancyIds) AND season_periods.id = prices.season_period_id AND season_periods.status=1 $str_status_check  group by season_period_id, option_id order by occupancy_id DESC, is_default, sell_price", [$this->noOfPassengers, $this->noOfPassengers, $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);
        $priceObjdata = DB::select("select prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id AND price_bands.min<=? AND price_bands.max>=?) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) AND occ.id IN ($this->occupancyIds) and service_options.status=?  group by season_period_id, option_id order by occupancy_id DESC, is_default, sell_price", [$this->noOfPassengers, $this->noOfPassengers, $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd, $this->serviceOptionStatus]);


        $priceObjdata1 = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join season_periods on (prices.season_period_id=season_periods.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id AND price_bands.min<=? AND price_bands.max>=?) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND prices.season_period_id IN (select id from season_periods where  start<=? AND end>=? OR start<=? AND end>=?) and occ.id IN ($this->occupancyIds) AND season_periods.id = prices.season_period_id AND season_periods.status=1  $str_status_check  group by season_period_id, option_id order by occupancy_id DESC, is_default, sell_price", [$this->noOfPassengers, $this->noOfPassengers, $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);



        /// if price band is exists for this service
        $priceObjdata1_3 = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id ) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=$this->ratesServiceId) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<='$this->startDate' AND prices.season_period_end>='$this->startDate' OR prices.season_period_start<='$this->actualEnd' AND prices.season_period_end>='$this->actualEnd') $str_status_check  order by price_bands.max DESC, is_default, sell_price limit 1");



        if(!empty($priceObjdata)) { // with db price table chnages
            return $priceObjdata;
        } else if(!empty($priceObjdata1_3)) {
            return $priceObjdata1_3;
        } else if(!empty($priceObjdata1)) { // with db price table chnages // org
           return $priceObjdata1;
        } else {
            //////////////////////////

            $getOccupanciesObj = DB::select("select prices.status as price_status, prices.id as price_id, service_policies.price_id, service_policies.charging_policy_id, service_policies.id as service_policies_id, policy_price_bands.id as policy_price_bands_id, policy_price_bands.price_band_id , price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, occupancies.max_adults,occupancies.id AS occupancies_id
from
 prices , service_policies, policy_price_bands, price_bands JOIN occupancies ON (occupancies.max_adults = price_bands.max)
where
prices.service_id=? AND prices.id = service_policies.price_id AND  policy_price_bands.service_policy_id = service_policies.id AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) AND policy_price_bands.price_band_id = price_bands.id $str_status_check1 ", [$this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);

        $str_occupanyId = '';
           foreach($getOccupanciesObj as $getOccupancyObj) {
               //echo $getOccupancyObj->occupancies_id."\n";
               $str_occupanyId = $str_occupanyId.$getOccupancyObj->occupancies_id.',';
               $noOfPassengers = $getOccupancyObj->price_band_max;
           }
           if($str_occupanyId != '' && (int)$noOfPassengers > 0) {
               $str_occupanyId = $str_occupanyId.'14';

            $priceObjdata2 = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id AND price_bands.min<=? AND price_bands.max>=?) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) AND occ.id IN ($str_occupanyId) $str_status_check  group by season_period_id, option_id order by occupancy_id DESC, is_default, sell_price", [$noOfPassengers, $noOfPassengers, $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);

            //////////////////////////
            return $priceObjdata2;
           }
           return ;

        }

    }

    private function serviceOptionsAndRatesWithOutPriceBrand() {

        $str_status_check = ' AND prices.status='.$this->serviceOptionStatus.' and service_options.status= '.$this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';

        }



//        $priceObjdata = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id,buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from  season_periods, prices join service_options on (prices.priceable_id = service_options.id)  join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) and occ.id IN ($this->occupancyIds) AND season_periods.id = prices.season_period_id AND season_periods.status=1 $str_status_check  group by season_period_id, option_id order by occupancy_id  DESC, is_default, sell_price", [ $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);

        $priceObjdata = DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id,buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id)  join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) and occ.id IN ($this->occupancyIds) and service_options.status=?  group by season_period_id, option_id order by occupancy_id  DESC, is_default, sell_price", [ $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd, $this->serviceOptionStatus]);


        if(!empty($priceObjdata)) { // with db price table chnages
            return $priceObjdata;
        } else { // org
            return DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id,buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, service_options.is_default as is_default, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join season_periods on (prices.season_period_id=season_periods.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' AND prices.season_period_id IN (select id from season_periods where  start<=? AND end>=? OR start<=? AND end>=? AND status = 1) and occ.id IN ($this->occupancyIds)  $str_status_check  group by season_period_id, option_id order by occupancy_id  DESC, is_default, sell_price", [ $this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);
        }



    }

    public function serviceExtrasAndRates($checkforError=array()) {

        $str_status_check = ' AND prices.status='. $this->serviceOptionStatus.' and service_extras.status= '. $this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';
        }

        //$serviceExtrasAndRates =  DB::select("select service_extras.status as extra_status, prices.status as price_status, buy_price, sell_price, service_extras.name as extra_name, service_extras.is_default as is_default, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, priceable_id as extra_id, prices.id as price_id, service_extras.ts_id as extra_tsid from season_periods, prices join service_extras on (prices.priceable_id = service_extras.id AND priceable_type LIKE '%ServiceExtra') join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join week_prices on (prices.id = week_prices.price_id) WHERE prices.service_id=? AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?)  AND season_periods.id = prices.season_period_id AND season_periods.status=1 $str_status_check  group by season_period_id, extra_id order by is_default, sell_price", [$this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd, $this->serviceOptionStatus]);

        $serviceExtrasAndRates =  DB::select("select service_extras.status as extra_status, prices.status as price_status, buy_price, sell_price, service_extras.name as extra_name, service_extras.is_default as is_default, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, priceable_id as extra_id, prices.id as price_id, service_extras.ts_id as extra_tsid from prices join service_extras on (prices.priceable_id = service_extras.id AND priceable_type LIKE '%ServiceExtra') join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join week_prices on (prices.id = week_prices.price_id) WHERE prices.service_id=? AND (prices.season_period_start<=? AND prices.season_period_end>=? OR prices.season_period_start<=? AND prices.season_period_end>=?) AND service_extras.status=?  group by season_period_id, extra_id order by is_default, sell_price", [$this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd, $this->serviceOptionStatus]);



        if(empty($serviceExtrasAndRates)) { // org

           $serviceExtrasAndRates =  DB::select("select service_extras.status as extra_status, prices.status as price_status, buy_price, sell_price, service_extras.name as extra_name, service_extras.is_default as is_default, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, priceable_id as extra_id, prices.id as price_id, service_extras.ts_id as extra_tsid from prices join service_extras on (prices.priceable_id = service_extras.id AND priceable_type LIKE '%ServiceExtra') join season_periods on (prices.season_period_id=season_periods.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join week_prices on (prices.id = week_prices.price_id) WHERE prices.service_id=? AND season_period_id IN (select id from season_periods where  start<=? AND end>=? OR start<=? AND end>=? and status = 1) $str_status_check  group by season_period_id, extra_id order by is_default, sell_price", [$this->ratesServiceId, $this->startDate, $this->startDate, $this->actualEnd, $this->actualEnd]);
        }


        if(empty($serviceExtrasAndRates) && !empty($checkforError)) {

            if(in_array('contractPeriod',$checkforError)) {
                $serviceExtrasAndRates = $this->serviceExtrasAndRatesWitoutStartAndEndDate();
                $serviceExtrasAndRates['Error'] = 'ContractPeriodError';
                return $serviceExtrasAndRates;
            }
            return;

        } else {
            return $serviceExtrasAndRates;
        }

    }

    public function getServiceWithCurrency($serviceId) {
        //return Service::with('currency')->find($serviceId);
        return Service::find($serviceId);
    }

    private function setParams($service, $startDate, $requestedCurrency, $rooms = array(), $totalNights = 1, $endDate = NULL, $quantity_g = NULL, $noOfPassengers_g = NULL, $isStatusCheckFlag=true) {

        //$baseCurrency = $service->currency->code;
/*
        if (!empty($requestedCurrency)) {
        //if (0) {
            $exchangeRate = $this->exchangeRateRepository->exchangeRate($baseCurrency, $requestedCurrency);
        } else {
            $exchangeRate = 1;
            $requestedCurrency = $baseCurrency;
        }*/

        if ($endDate != NULL) {
            $carbonEnd = Carbon::parse($endDate);
            $totalNights = $carbonEnd->diffInDays(Carbon::parse($startDate));
            $actualEnd = $carbonEnd->subDay()->format('Y-m-d');
        } else {
            $carbonEnd = Carbon::parse($startDate)->addDays($totalNights);
            $endDate = $carbonEnd->format('Y-m-d');
            $actualEnd = $carbonEnd->subDay()->format('Y-m-d');
        }
        $startDate = Carbon::parse($startDate)->format('Y-m-d');

        $this->tsServiceId = $service->ts_id;
        $this->ratesServiceId = $service->id;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->actualEnd = $actualEnd;
        $this->totalNights = $totalNights;
        $this->serviceOptionStatus = 1;
        $this->currencyCode = $requestedCurrency;
        //$this->exchangeRate = $exchangeRate;
        $this->nNightsChargingPolicy = false;
        $this->nNightsChargingPolicyError = false;

        $this->isStatusCheckFlag = $isStatusCheckFlag;

        if(empty($this->service_type_id)) {
            $this->service_type_id = $service->service_type_id;
        }


if($this->service_type_id == 1) {
    if (isset($rooms) && count($rooms) > 0) {
            $this->occupancyIds = implode(array_keys($rooms), ",");
            if(preg_match("/all|none|14/i", $this->occupancyIds)) {
                $this->occupancyIds = '1,2,3,4,5,6,7,8,9,10,11,12,1,3,14,5,16,17,8,19,20,21';
            } else {
             $this->occupancyIds = $this->occupancyIds.",14";
            }
            $this->noOfPassengers = array_reduce(array_values($rooms), function($total = 0, $ra) {
                return $total += $ra["NO_OF_PASSENGERS"];
            });
        }
} else {
    if (isset($rooms) && count($rooms) > 0) {
     $this->occupancyIds = implode(array_keys($rooms), ",");
            if(preg_match("/all|none|14/i", $this->occupancyIds)) {
                $this->occupancyIds = '1,2,3,4,5,6,7,8,9,10,11,12,1,3,14,5,16,17,8,19,20,21';
            } else {
             $this->occupancyIds = $this->occupancyIds.",14";
            }
            $this->noOfPassengers = array_reduce(array_values($rooms), function($total = 0, $ra) {
                return $total += $ra["NO_OF_PASSENGERS"];
            });
            $this->quantityg = array_reduce(array_values($rooms), function($total = 0, $ra) {
                return $total += $ra["QUANTITY"];
            });
    } else {
     $this->noOfPassengers = $noOfPassengers_g;
     $this->quantityg = $quantity_g;
    }


}



    }

    private function getDisplayPrice($price) {
        return ceil($price * $this->exchangeRate);
    }

    public function getNightsCount($seasonStart, $seasonEnd, $dayStart, $dayEnd, $totalNights) {
        $seasonStart = Carbon::parse($seasonStart);
        $seasonEnd = Carbon::parse($seasonEnd)->addDay();
        $dayStart = Carbon::parse($dayStart);
        $dayEnd = Carbon::parse($dayEnd);

        if ($dayStart > $seasonEnd || $seasonStart > $dayEnd || $seasonEnd < $seasonStart || $dayEnd < $dayStart) {
            return 0;
        }

        $start = $seasonStart < $dayStart ? $dayStart : $seasonStart;
        $end = $seasonEnd < $dayEnd ? $seasonEnd : $dayEnd;
        $nights = $end->diffInDays($start);
        return ($totalNights > 1 && $nights == 0) ? $nights + 1 : $nights;
    }

    // Calculating multiplicand with respect to either charging policy or Price bands.
    public function multiplicandByChargingPolicy($policyObj, $quantity, $noOfPeople) {

        if((int)$this->service_type_id > 0 && (int)$this->service_type_id != 1) {
            //$this->noOfPassengers = $noOfPassengers_g;
            //$this->quantityg = $quantity_g;
            $quantity = $this->quantityg;
            $noOfPeople = $this->noOfPassengers;
        }

        $multiplicand = 1;
        $chargingPolicyMultiplicand['multiplicand'] = $multiplicand;
        if (isset($policyObj->policy_id) && !empty($policyObj->policy_id)) {
            $this->nNightsChargingPolicyError = false;
            if ($policyObj->policy_name != "Fast Build") {

//                print('$policyObj <xmp>');
//                print_r($policyObj);
//                print('</xmp>');
//
                $isRoomBased = $policyObj->room_based; // 1= yes
                $dayDuration = $policyObj->day_duration; // 1= yes
                $nights = $this->getNightsCount($policyObj->start, $policyObj->end, $this->startDate, $this->endDate, $this->totalNights);
                $nights += ($nights == 0 ? 1 : 0);

                if ($isRoomBased == '1') { // unit/room based
//                    die('room based');
                    if ($dayDuration <= '1') { // per unit/room per day/night
                        $multiplicand *= $nights * $quantity;
                    } else { // per unit/room per N day/night

                        if(($nights  % $dayDuration) !== 0) {
                            $this->nNightsChargingPolicyError = true;
                        }

                        $nnights = ceil($nights / $dayDuration);
                        $multiplicand *= $nnights * $quantity;
                        $chargingPolicyMultiplicand['nnights'] = $nnights;
                        $chargingPolicyMultiplicand['nights'] = $nights;
                        $chargingPolicyMultiplicand['dayDuration'] = $dayDuration;
                        $this->nNightsChargingPolicy = true;
                    }
                } else { // person based
//                    die('person based');
                    if ($dayDuration <= '1') {  // per person per day/night
                        $multiplicand *= $noOfPeople * $nights;
                    } else {  // per person per  N day/night

                        if(($nights  % $dayDuration) !== 0) {
                            $this->nNightsChargingPolicyError = true;
                        }

                        $nnights = ceil($nights / $dayDuration);
                        $multiplicand *= $noOfPeople * $nnights;
                        $chargingPolicyMultiplicand['nnights'] = $nnights;
                        $chargingPolicyMultiplicand['nights'] = $nights;
                        $chargingPolicyMultiplicand['dayDuration'] = $dayDuration;
                        $this->nNightsChargingPolicy = true;
                    }
                }
                $chargingPolicyMultiplicand['multiplicand'] = $multiplicand;
            }
        }

        return $chargingPolicyMultiplicand;
    }

    //public function getPriceByConsideringWeekDay($season_period_id, $option_id, $totalNights, $chargingPolicyMultiplicand, $price_id = NULL) {
    public function getPriceByConsideringWeekDay($priceObj, $totalNights, $chargingPolicyMultiplicand, $toCurrency,$rooms = array()) {

        $price_id = NULL;
        $season_period_id = $priceObj->season_period_id;
        if(isset($priceObj->option_id)) {
            $option_id = $priceObj->option_id;
        } else {
            $option_id = $priceObj->extra_id;
        }


        $nights = $this->getNightsCount($priceObj->start, $priceObj->end, $this->startDate, $this->endDate, $this->totalNights);
        $nights += ($nights == 0 ? 1 : 0);

        $startDate = $this->startDate;
        if(strtotime($priceObj->start) > strtotime($this->startDate)) {
            $startDate = $priceObj->start;
        }

        $buy_price = 0;
        $sell_price = 0;
        $weekDayPriceArr = array();

        $totalNights = $nights;
        //for ($count = 0; $count < $totalNights; $count++) {
        for ($count = 0; $count < $totalNights; $count++) {
            if ($count == 0) {
                $startDate_day = Carbon::parse($startDate)->format('l');
            } else {
                $startDate = Carbon::parse($startDate)->addDay();
                $startDate_day = $startDate->format('l');
            }

            $price_id_qry = '';
            if ($price_id != NULL) { // useful for price bands
                $price_id_qry = " and p.id = " . $price_id;
            }

            $weekDayPriceObj = DB::select("select p.buy_price, p.sell_price, p.currency_id as prices_currency_id from prices p, week_prices wp where p.service_id = " . $this->ratesServiceId . " and p.id = wp.price_id and wp." . strtolower($startDate_day) . " = 1 and p.season_period_id = " . $season_period_id . " and p.priceable_id=" . $option_id . $price_id_qry);

            ///// currency for price table -  Start
                if(isset($weekDayPriceObj->prices_currency_id)) {
                    $baseCurrency_id = $weekDayPriceObj->prices_currency_id;
                    $pricesCurrencyObj = DB::table('currencies')->where('id', '=', $baseCurrency_id)->get();
                    $baseCurrency = $pricesCurrencyObj[0]->code;
                    $requestedCurrency = $toCurrency;
                    $exchangeRate = $this->exchangeRateRepository->exchangeRate($baseCurrency, $requestedCurrency);
                    if(!empty($exchangeRate)) {
                        $this->exchangeRate = $exchangeRate;
                    } else {
                        $this->exchangeRate = 1;
                        $requestedCurrency = $baseCurrency;
                    }
                    $this->currencyCode = $requestedCurrency;
                }
                ///// currency for price table -  End


            if (!empty($weekDayPriceObj)) {
                $buy_price += $this->getDisplayPrice($weekDayPriceObj[0]->buy_price);
                $sell_price += $this->getDisplayPrice($weekDayPriceObj[0]->sell_price);
            }
            if (isset($chargingPolicyMultiplicand['dayDuration']) && $chargingPolicyMultiplicand['dayDuration'] > 1) {
                $totalNights -= $chargingPolicyMultiplicand['dayDuration'] - 1;
                $startDate = Carbon::parse($startDate)->addWeekdays(($chargingPolicyMultiplicand['dayDuration'] - 1));
            }
        }


        if ($buy_price > 0) {
            $weekDayPriceArr['buy_price'] = $buy_price;
            $weekDayPriceArr['sell_price'] = $sell_price;
            return $weekDayPriceArr;
        }

//        if($this->isSeasonPeriodOverlap($priceObj) === true) {
//            $weekDayPriceArr = $this->getSeasonPeriodOverlapPrices($priceObj, $nights, $chargingPolicyMultiplicand, $toCurrency,$rooms);
//            return $weekDayPriceArr;
//        }

        return false;
    }

    public function calculateTotalServiceRate($service, $startDate, $toCurrency, $rooms, $totalNights, $quantity_g, $noOfPassengers_g, $isStatusCheckFlag=true, $checkforError=array()) {

        $this->setParams($service, $startDate, $toCurrency, $rooms, $totalNights, NULL, $quantity_g, $noOfPassengers_g, $isStatusCheckFlag);

        $serviceOptions = $this->serviceOptionsAndRates($checkforError);



        $respArray = array(
            'GetServicesPricesAndAvailabilityResult' => array(
                'Services' => array(
                    'PriceAndAvailabilityService' => array(
                        'ServiceID' => $this->tsServiceId,
                        'ServiceCode' => $this->ratesServiceId,
                    )
                ),
                'Warnings' => (object) array()
            )
        );


        if(!empty($serviceOptions['Error']) && count($serviceOptions) > 1) {
            if($serviceOptions['Error'] == 'ContractPeriodError') {
                foreach($serviceOptions as $serviceOption) {
                    if(is_object($serviceOption)) {
                        $_date_arr[] = $serviceOption->start;
                        $_date_arr[] = $serviceOption->end;
                    }
                }
                sort($_date_arr);

                $respArray["GetServicesPricesAndAvailabilityResult"]["Errors"] = json_decode(json_encode(['Error' => [ 'Description' => ' Service is being planed out of date range selected. Please contact PM team to update new dates.',
                'valid-contract-date-range' => ( !empty($_date_arr) ? ($_date_arr[0]." to ". end($_date_arr)) : '')
                    ]]));
            }
        } else if (empty($serviceOptions) || is_null($serviceOptions) || (count($serviceOptions) ==1 && isset($serviceOptions['Error'])) ) {
            $respArray["GetServicesPricesAndAvailabilityResult"]["Errors"] = json_decode(json_encode(['Error' => [ 'Description' => 'Service not found']]));
        } else {
            $respArray["GetServicesPricesAndAvailabilityResult"]["Errors"] = (object) array();
            $sell_price = 0;
            $buy_price = 0;
            foreach ($serviceOptions as $key => $price) {

                if($price->sell_price == 0) {
//                    continue;
                }

                $weekDaynights = 0;

//                echo '$price->sell_price : '.$price->sell_price.'<br>';

                ///// currency for price table -  Start
                if(isset($price->prices_currency_id)) {
                    $baseCurrency_id = $price->prices_currency_id;
                    $pricesCurrencyObj = DB::table('currencies')->where('id', '=', $baseCurrency_id)->get();
                    $baseCurrency = $pricesCurrencyObj[0]->code;
                    $requestedCurrency = $toCurrency;
                    $exchangeRate = $this->exchangeRateRepository->exchangeRate($baseCurrency, $requestedCurrency);
                    if(!empty($exchangeRate)) {
                        $this->exchangeRate = $exchangeRate;
                    } else {
                        $this->exchangeRate = 1;
                        $requestedCurrency = $baseCurrency;
                    }

                    if(trim($requestedCurrency) == '') {
                        $this->currencyCode = $baseCurrency;
                    } else {
                        $this->currencyCode = $requestedCurrency;
                    }

                }
                //echo '$this->exchangeRate : '.$this->exchangeRate.'<br>';
                ///// currency for price table -  End
                //
                //
                ///// meal plan for price table -  End
                if(isset($price->prices_meal_plan_id) && (int)$price->prices_meal_plan_id > 0) {

                    $pricesMealsObj = DB::table('meals')->where('id', '=', $price->prices_meal_plan_id)->get();
                    $price->meal_id = $price->prices_meal_plan_id;
                    $price->meal_name = $pricesMealsObj[0]->name;
                }
                ///// meal plan  for price table -  End

                $sell_price = $this->getDisplayPrice($price->sell_price);
                $buy_price = $this->getDisplayPrice($price->buy_price);

                if(!isset($rooms[$price->occupancy_id]["NO_OF_PASSENGERS"])) {
                    if(isset($rooms['ALL'])) {
                        $rooms[$price->occupancy_id]["QUANTITY"] = $rooms['ALL']["QUANTITY"];
                        $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"] = $rooms['ALL']["NO_OF_PASSENGERS"];
                    } else if(isset($rooms['NONE'])) {
                        $rooms[$price->occupancy_id]["QUANTITY"] = $rooms['NONE']["QUANTITY"];
                        $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"] = $rooms['NONE']["NO_OF_PASSENGERS"];
                    } else if(isset($rooms['14'])) {
                        $rooms[$price->occupancy_id]["QUANTITY"] = $rooms['14']["QUANTITY"];
                        $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"] = $rooms['14']["NO_OF_PASSENGERS"];
                    } else {
                        $rooms[$price->occupancy_id]["QUANTITY"] = !empty($this->quantityg) ? $this->quantityg : 1;;
                        $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"] = !empty($this->noOfPassengers) ? $this->noOfPassengers : 1;
                    }
                }

                $chargingPolicyMultiplicand = $this->multiplicandByChargingPolicy($price, $rooms[$price->occupancy_id]["QUANTITY"], $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"]);
                $multiplicand = $chargingPolicyMultiplicand['multiplicand'];

                if($this->nNightsChargingPolicyError === false) { // nNightsChargingPolicyError - Start
                    // Getting total price for a option with respect to Week Days if exists -  Start
                    $weekDaynights = $this->totalNights + ($this->totalNights == 0 ? 1 : 0);
                    if (!isset($price->price_id)) {
                        $price->price_id = NULL;
                    }
                    $weekDayPriceArr = $this->getPriceByConsideringWeekDay($price, $weekDaynights, $chargingPolicyMultiplicand,$toCurrency,$rooms); // getting price for per night per person
                    if (!empty($weekDayPriceArr)) {
                        $buy_price = $weekDayPriceArr['buy_price'];
                        $sell_price = $weekDayPriceArr['sell_price'];
                        $multiplicand = $rooms[$price->occupancy_id]["QUANTITY"]; // if room based.. as we are already calculating price for each day -  this is Quantity based
                        // if the charging policy is based on person -  this is Person based
                        if(!empty($weekDayPriceArr['days_overlap']) && $weekDayPriceArr['days_overlap'] == 1) {
                            $multiplicand = 1;
                        } else if ($price->room_based == 0 || (isset($price->price_band_id) && !empty($price->price_band_id))) {
                            $multiplicand *= $rooms[$price->occupancy_id]["NO_OF_PASSENGERS"];
                        }
                    } else {
                        $weekDaynights = 1;
                    }
                    // Getting total price for a option with respect to Week Days if exists -  End

                    if (!empty($price->policy_id) || !empty($price->price_band_id)) {

                        if (!isset($totalBuyingPrice[$price->option_id])) {
                            $totalBuyingPrice[$price->option_id] = $totalSellingPrice[$price->option_id] = 0;
                        }

                        $mealPlan = ["MealPlanID" => $price->meal_id,
                            "MealPlanName" => $price->meal_name,
                            "MealPlanCode" => $price->meal_name . $price->meal_id];


                        $totalBuyingPrice[$price->option_id] += ceil($buy_price * $multiplicand);
                        $totalSellingPrice[$price->option_id] += ceil($sell_price * $multiplicand);


                        $values = array(
                            "MaxChild" => $price->max_children,
                            "MaxAdult" => $price->max_adults,
                            "OptionOccupancy" => array(
                                "Adults" => $price->max_adults,
                                "Children" => $price->max_children),
                            "Occupancy" => $price->occupancy_id,
                            "Currency" => $this->currencyCode,
                            "TotalSellingPrice" => $totalSellingPrice[$price->option_id],
                            "TotalBuyingPrice" => $totalBuyingPrice[$price->option_id],
                            "RatesOptionID" => $price->option_id,
                            "OptionID" => (($this->tsServiceId > 500000) ? $price->option_id : $price->ts_option_id),
                            "OptionStatus" => (!empty($price->option_status) ? $price->option_status : 'NA'),
                            "PriceStatus" => (!empty($price->price_status) ? $price->price_status : 'NA'),
                            "ServiceOptionName" => $price->option_name,
                            "ServiceOptionIsDefault" => (isset($price->is_default) ? $price->is_default : 'NO')
                        );

                        $respArray["GetServicesPricesAndAvailabilityResult"]["Services"]["PriceAndAvailabilityService"]["ServiceOptions"]["PriceAndAvailabilityResponseServiceOption"][$price->option_id] = $values;

                        if(!isset($weekDaynights)) {
                            $weekDaynights = 1;
                        }
                        if($this->nNightsChargingPolicy === false) {
                            $weekDaynights = 1;
                        }

                        $optionPrices[$price->option_id] = [
                            "BuyPrice" => $buy_price/$weekDaynights,
                            "SellPrice" => $sell_price/$weekDaynights,
                            "MealPlan" => $mealPlan,
                            "ChargingPolicyName" => $price->policy_name];

                        $respArray["GetServicesPricesAndAvailabilityResult"]["Services"]["PriceAndAvailabilityService"]["ServiceOptions"]["PriceAndAvailabilityResponseServiceOption"][$price->option_id]["Prices"]["PriceAndAvailabilityResponsePricing"] = $optionPrices[$price->option_id];
                    }

                } //  nNightsChargingPolicyError - END

            }
            if(isset($respArray["GetServicesPricesAndAvailabilityResult"]["Services"]["PriceAndAvailabilityService"]["ServiceOptions"])) {
                $priceValues = array_values($respArray["GetServicesPricesAndAvailabilityResult"]["Services"]["PriceAndAvailabilityService"]["ServiceOptions"]["PriceAndAvailabilityResponseServiceOption"]);
                $respArray["GetServicesPricesAndAvailabilityResult"]["Services"]["PriceAndAvailabilityService"]["ServiceOptions"]["PriceAndAvailabilityResponseServiceOption"] = $priceValues;
            }

            if($this->nNightsChargingPolicyError === true && empty($respArray['GetServicesPricesAndAvailabilityResult']['Services']['PriceAndAvailabilityService']['ServiceOptions'])) {
                $respArray["GetServicesPricesAndAvailabilityResult"]["Errors"] = json_decode(json_encode(['Error' => [ 'type' => 'nNightsChargingPolicyError','Description' => 'You are planning service for more than defined number of nights. Please contact PM team']]));
            }
        }
        $respArray["GetServicesPricesAndAvailabilityResult"]['isResponseFromTS'] = "NO";

        return $respArray;
    }

    function calculateServiceExtraRate($service, $startDate, $endDate, $toCurrency, $noOfPeople,$isStatusCheckFlag=true, $checkforError=array()) {

        $this->setParams($service, $startDate, $toCurrency, NULL, NULL, $endDate, NULL, NULL,$isStatusCheckFlag);

        $serviceExtras = $this->serviceExtrasAndRates($checkforError);

        if(!empty($serviceExtras['Error'])  && count($serviceExtras) > 1) {
            if($serviceExtras['Error'] == 'ContractPeriodError') {
                foreach($serviceExtras as $serviceExtra) {
                    if(is_object($serviceExtra)) {
                        $_date_arr[] = $serviceExtra->start;
                        $_date_arr[] = $serviceExtra->end;
                    }
                }
                sort($_date_arr);

                $responseValue = array(
                    "Errors" => [ 'Description' => ' Service is being planed out of date range selected. Please contact PM team to update new dates.',
                'valid-contract-date-range' => ( !empty($_date_arr) ? ($_date_arr[0]." to ". end($_date_arr)) : '')],
                    "ServiceId" => $service->ts_id,
                    "ServiceCode" => $service->id,
                    "ServiceName" => $service->extra_name,
                    "ServiceTypeId" => $service->service_type_id,
                    "ResponseList" => array()
                );
                $respArray["ServiceExtrasAndPricesResponse"] = $responseValue;
            }

        } else if (empty($serviceExtras) || is_null($serviceExtras) || (count($serviceExtras) ==1 && isset($serviceExtras['Error'])) ) {
            $responseValue = array(
                "Errors" => (object) array(),
                "ServiceId" => 0,
                "ServiceCode" => 0,
                "ServiceName" => 0,
                "ServiceTypeId" => 0,
                "ResponseList" => array()
            );
            $respArray["ServiceExtrasAndPricesResponse"] = $responseValue;
        } else {
            $responseValue = array(
                "Errors" => (object) array(),
                "ServiceId" => $service->ts_id,
                "ServiceCode" => $service->id,
                "ServiceName" => $service->extra_name,
                "ServiceTypeId" => $service->service_type_id,
                "ResponseList" => array()
            );
            $respArray["ServiceExtrasAndPricesResponse"] = $responseValue;

            foreach ($serviceExtras as $key => $extra) {

                if($extra->sell_price == 0) {
//                    continue;
                }

                ///// currency for price table -  Start
                if(isset($extra->prices_currency_id)) {

                    $baseCurrency_id = $extra->prices_currency_id;
                    $pricesCurrencyObj = DB::table('currencies')->where('id', '=', $baseCurrency_id)->get();
                    $baseCurrency = $pricesCurrencyObj[0]->code;
                    $requestedCurrency = $toCurrency;
                    $exchangeRate = $this->exchangeRateRepository->exchangeRate($baseCurrency, $requestedCurrency);
                    if(!empty($exchangeRate)) {
                        $this->exchangeRate = $exchangeRate;
                    } else {
                        $this->exchangeRate = 1;
                        $requestedCurrency = $baseCurrency;
                    }
                    $this->currencyCode = $requestedCurrency;
                }
                ///// currency for price table -  End

                $sell_price = $this->getDisplayPrice($extra->sell_price);
                $buy_price = $this->getDisplayPrice($extra->buy_price);

                $chargingPolicyMultiplicand = $this->multiplicandByChargingPolicy($extra, 1, 1); // for qty = 1 , no of passenger = 1
                $multiplicand = $chargingPolicyMultiplicand['multiplicand'];
                if($this->nNightsChargingPolicyError === false) { // nNightsChargingPolicyError - Start
                    $weekDaynights = $this->totalNights + (($this->totalNights == 0 ? 1 : 0));
                    $weekDayPriceArr = $this->getPriceByConsideringWeekDay($extra, $weekDaynights, $chargingPolicyMultiplicand,$toCurrency); // getting price for per night per person


                    if (!empty($weekDayPriceArr)) {
                        $sell_price = $weekDayPriceArr['sell_price'];
                        $buy_price = $weekDayPriceArr['buy_price'];
                        $multiplicand = 1; // if room based.. as we are already calculating price for each day
                    }
                    if($multiplicand == 0) {
                      $multiplicand = 1;
                    }

                    $price = array(
                        "PriceId" => $extra->price_id,
                        "PriceDate" => $extra->start,
                        "CurrencyIsoCode" => $this->currencyCode,
                        "PriceAmount" => $sell_price,
                        "BuyPrice" => $buy_price,
                        "ChargingPolicyName" => $extra->policy_name
                    );
                    //$respArray["ServiceExtrasAndPricesResponse"]["ResponseList"]["ServiceExtras"][][$key]["ExtraPrices"]["ServiceExtraPrice"] = $price;


                    $value = array(
                        "ExtraMandatory" => false,
                        "OccupancyTypeID" => 0,
                        "ServiceTypeTypeID" => 1,
                        "ServiceTypeTypeName" => "Others",
                        "MaxAdults" => 100,
                        "MaxChild" => 0,
                        "MinAdults" => 0,
                        "MinChild" => 0,
                        "ChildMaxAge" => 0,
                        "ServiceExtraId" => $extra->extra_tsid,
                        "ExtraStatus" => (!empty($extra->extra_status) ? $extra->extra_status : 'NA'),
                        "PriceStatus" => (!empty($extra->price_status) ? $extra->price_status : 'NA'),
                        "ServiceExtraCode" => $extra->extra_id,
                        "ServiceTypeExtraName" => $extra->extra_name,
                        "TotalBuyPrice" => ceil($buy_price * $multiplicand),
                        "TOTALPRICE" => ceil($sell_price * $multiplicand),
                        "ExtraPrices" => array('ServiceExtraPrice' => $price)
                    );

                    $respArray["ServiceExtrasAndPricesResponse"]["ResponseList"]["ServiceExtras"][] = $value;

                } // nNightsChargingPolicyError - End
            }

            if($this->nNightsChargingPolicyError === true && empty($respArray['ServiceExtrasAndPricesResponse']['ResponseList']['ServiceExtras'])) {
                $respArray["ServiceExtrasAndPricesResponse"]["Errors"] = json_decode(json_encode(['Error' => [ 'type' => 'nNightsChargingPolicyError','Description' => 'You are planning service for more than defined number of nights. Please contact PM team']]));
            }            
        }
        $respArray["ServiceExtrasAndPricesResponse"]["isResponseFromTS"] = "NO";
        return $respArray;
    }



    private function serviceOptionsAndRatesWitoutStartAndEndDate() {
        $serviceOptionsAndRates = $this->serviceOptionsAndRatesWithPriceBrandWitoutStartAndEndDate();
        if (count($serviceOptionsAndRates) == 0) { // if Record doesn't exits with respect to price band
            $serviceOptionsAndRates = $this->serviceOptionsAndRatesWithOutPriceBrandWitoutStartAndEndDate();

        }
        if(!empty($serviceOptionsAndRates)) {
            return $serviceOptionsAndRates;
        }
    }

    private function serviceOptionsAndRatesWithPriceBrandWitoutStartAndEndDate() {

        $str_status_check = ' and service_options.status= '.$this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';
        }

        return DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id, buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, price_bands.id as price_band_id, price_bands.min as price_band_min, price_bands.max as price_band_max, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join season_periods on (prices.season_period_id=season_periods.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) right join ( policy_price_bands join price_bands on (policy_price_bands.price_band_id = price_bands.id AND price_bands.min<=? AND price_bands.max>=?) ) on (policy_price_bands.service_policy_id = service_policies.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' and occ.id IN ($this->occupancyIds) AND season_periods.status = 1  $str_status_check  group by season_period_id, option_id", [$this->noOfPassengers, $this->noOfPassengers, $this->ratesServiceId]);
    }

    private function serviceOptionsAndRatesWithOutPriceBrandWitoutStartAndEndDate() {

        $str_status_check = ' and service_options.status= '.$this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';
        }

        return DB::select("select service_options.status as option_status, prices.status as price_status, prices.id as price_id,buy_price, sell_price, monday, tuesday, wednesday, thursday, friday, saturday, sunday, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, service_options.ts_id as ts_option_id, priceable_id as option_id, service_options.name as option_name, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, occ.name as occupancy_name, occ.id as occupancy_id, max_adults, max_children, meals.id as meal_id, meals.name as meal_name from prices join service_options on (prices.priceable_id = service_options.id) join season_periods on (prices.season_period_id=season_periods.id) join occupancies occ on (service_options.occupancy_id=occ.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join ( meal_options join meals on (meal_options.meal_id = meals.id) ) on (meal_options.service_option_id = service_options.id) left join week_prices on (prices.id = week_prices.price_id) where priceable_id IN (select id from service_options where service_id=?) AND priceable_type LIKE '%ServiceOption' and occ.id IN ($this->occupancyIds) AND season_periods.status=1 $str_status_check  group by season_period_id, option_id", [ $this->ratesServiceId]);
    }

    public function serviceExtrasAndRatesWitoutStartAndEndDate() {

        $str_status_check = ' and service_extras.status= '. $this->serviceOptionStatus.' ';

        if($this->isStatusCheckFlag === false) {
            $str_status_check = '';
        }

        return DB::select("select service_extras.status as extra_status, prices.status as price_status, buy_price, sell_price, service_extras.name as extra_name, prices.season_period_id, start, end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration, priceable_id as extra_id, prices.id as price_id, service_extras.ts_id as extra_tsid from prices join service_extras on (prices.priceable_id = service_extras.id AND priceable_type LIKE '%ServiceExtra') join season_periods on (prices.season_period_id=season_periods.id) join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id) left join week_prices on (prices.id = week_prices.price_id) WHERE prices.service_id=? and season_periods.status=1 $str_status_check  group by season_period_id, extra_id", [$this->ratesServiceId]);
    }

    // For activity code
    public function getServiceInfo($params) {
        $serviceInfoObjArr = array();
        $currencyObjArr = array();
        $response = array();

        $serviceTsId = $params['RequestObject']['ServiceID'];

        $serviceInfoObj = DB::table('services')
                ->join('regions', 'services.region_id', '=', 'regions.id')
                ->join('suppliers', 'services.supplier_id', '=', 'suppliers.id')
                ->join('service_types', 'services.service_type_id', '=', 'service_types.id')
                ->select('services.ts_id as service_ts_id','services.id as service_id','services.name as service_name','services.short_name as service_short_name', 'regions.ts_id as region_ts_id', 'regions.id as region_id', 'regions.name as region_name', 'regions.parent_id', 'suppliers.ts_id as supplier_ts_id', 'suppliers.id as supplier_id', 'suppliers.name as supplier_name', 'service_types.ts_id as service_type_ts_id', 'service_types.id as service_type_id', 'service_types.name as service_type_name')
                ->where('services.ts_id', '=', $serviceTsId)
                ->distinct()
                ->get();

        if(!empty($serviceInfoObj)) {
            $serviceInfoObjArr = json_decode(json_encode($serviceInfoObj), true);

            $response['ServiceInfoResponses'] = array(
                'ResponseList' => array(
                    'anyType' => array(
                        'enc_value' => array(
                            'ServiceID' => $serviceInfoObjArr[0]['service_ts_id'],
                            'SupplierID' => $serviceInfoObjArr[0]['supplier_ts_id'],
                            'ServiceShortName' => $serviceInfoObjArr[0]['service_short_name'],
                            'ServiceLongName' => $serviceInfoObjArr[0]['service_name'],
                            'ServiceTypeID' => $serviceInfoObjArr[0]['service_type_ts_id'],
                            'ServiceTypeName' => $serviceInfoObjArr[0]['service_type_name'],
                            'RegionID' => $serviceInfoObjArr[0]['region_ts_id']
                        )
                    )
                )
            );

            return $response;
        }

        return '';
    }

    private function isSeasonPeriodOverlap($priceObj)
    {
        if (isset($priceObj->option_id)) {
            $option_id = $priceObj->option_id;
            $str_priceable_type = 'option';
        } else {
            $option_id = $priceObj->extra_id;
            $str_priceable_type = 'extra';
        }

        $priceObj = DB::select("SELECT * FROM prices WHERE prices.priceable_id = ? AND prices.priceable_type LIKE '%".$str_priceable_type."%' AND prices.service_id = ? AND  ((prices.season_period_start >= ?) && (prices.season_period_start <= ?) || (prices.season_period_end >= ?) && (prices.season_period_end <= ?)) ", [$priceObj->option_id,$this->ratesServiceId,$this->startDate,$this->actualEnd,$this->startDate,$this->actualEnd]);

        if(count($priceObj) > 1) {
            return true;
        }
        return false;
    }

    private function getSeasonPeriodOverlapPrices($priceObj, $totalNights,
                                                  $chargingPolicyMultiplicand,
                                                  $toCurrency, $rooms)
    {

        $occupancy_id = $priceObj->occupancy_id;

        $price_id = NULL;
        if (isset($priceObj->option_id)) {
            $option_id = $priceObj->option_id;
            $str_priceable_type = 'option';
        } else {
            $option_id = $priceObj->extra_id;
            $str_priceable_type = 'extra';
        }

        $nights = $this->getNightsCount($priceObj->start, $priceObj->end,
            $this->startDate, $this->endDate, $this->totalNights);
        $nights = $this->totalNights-1;
        $nights += ($nights == 0 ? 1 : 0);
//echo '$nights : '.$nights."<br><br>";
        $buy_price  = 0;
        $sell_price = 0;
        $startDate  = $this->startDate;
        for ($count = 0; $count < $nights; $count++) {
            if ($count == 0) {
                $startDate_day = Carbon::parse($startDate)->format('l');
            } else {
                $startDate     = Carbon::parse($startDate)->addDay();
                $startDate_day = $startDate->format('l');
            }
            
            $tmpPriceObj = DB::select("SELECT prices.id as price_id, prices.buy_price, prices.sell_price, prices.season_period_id, prices.season_period_start as start, prices.season_period_end as end, prices.currency_id as prices_currency_id, prices.meal_plan_id as prices_meal_plan_id, priceable_id as option_id, charging_policies.id as policy_id, charging_policies.ts_id as policy_tsid, charging_policies.name as policy_name, charging_policies.room_based as room_based, charging_policies.day_duration as day_duration
FROM prices join service_policies on (service_policies.price_id = prices.id) join charging_policies on (service_policies.charging_policy_id = charging_policies.id)
WHERE prices.priceable_id = ?
AND prices.priceable_type LIKE '%".$str_priceable_type."%'
AND prices.service_id = ?
AND ( prices.season_period_start<= ? AND prices.season_period_end>= ? OR prices.season_period_start<=? AND prices.season_period_end>=? ) ",
                    [$priceObj->option_id, $this->ratesServiceId, $startDate, $startDate,
                    $startDate, $startDate]);

            $chargingPolicyMultiplicand = $this->multiplicandByChargingPolicy($tmpPriceObj,
                $rooms[$occupancy_id]["QUANTITY"],
                $rooms[$occupancy_id]["NO_OF_PASSENGERS"]);
            $multiplicand               = $chargingPolicyMultiplicand['multiplicand'];

            $buy_price += $this->getDisplayPrice(($tmpPriceObj[0]->buy_price * $multiplicand));
            $sell_price += $this->getDisplayPrice(($tmpPriceObj[0]->sell_price * $multiplicand));

//            echo $startDate." -> ".$tmpPriceObj[0]->buy_price.'<br>';
        }

        $weekDayPriceArr['buy_price']  = $buy_price;
        $weekDayPriceArr['sell_price'] = $sell_price;
        $weekDayPriceArr['days_overlap'] = 1;
        return $weekDayPriceArr;
    }

}