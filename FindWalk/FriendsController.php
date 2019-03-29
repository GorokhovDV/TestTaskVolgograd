<?php

namespace app\commands;

use yii\console\Controller;

class FriendsController extends Controller
{

    /**
     * Метод принимает координаты устройства и возвращает координаты параллелепипеда для дальнейшего поиска с учетом доверенного интервала
     * @param float $x - GPS координата широта
     * @param float $y - GPS координата долгота
     * @param int $z - высота
     * @param int $distance - дистанция относительно которой рассматриваем
     * @param int $fallibility - доверенный интервал в метрах
     * @return array - массив с координатами прямоугольника
     */
    public function coordinatesByDistance(float $x, float $y, int $z, int $distance, int $fallibility)
    {

        //Если позволите я не буду сейчас расписывать механизм перевода координат в прямоугольник, думаю для понимания принципа это не сильно важно

        return [
            'xMin' => (float)0,
            'xMax' => (float)0,
            'yMin' => (float)0,
            'yMax' => (float)0,
            'zMin' => (int)0,
            'zMax' => (int)0,
        ];
    }

    /**
     * Метод принимает идентификатор устройства и временную метку от которой искать последний сон. В этой функции происходит поиск всех сообщений типа трек начиная с последнего сообщения типа сон и до текущей метки времени или до следующей отметки "Сон"
     * @param int $device_id - идентификатор устройства относительного которого происходит поиск
     * @param int $timestamp - временная метка отнгосительно которой происходит поиск
     * @return array DataTrack
     */
    public function getLastTrackSessions($device_id, $timestamp)
    {
        //Берем последни сообщения типа трек начиная с момента последнего сна (определяется таймстемпом сообщения типа Акстивность где тип активности равен "Сон")
        return [
            DataTrack
        ];
    }

    /**
     * Метод нормализует временную метку приводя ее в минуты
     * @param int $timestamp - исходная временная метка
     * @return int
     */
    public function normolizeTimestampToMinutes($timestamp) {
        return  floor($timestamp / 60) * 60;
    }


    /**
     * По двум выборкам треков ищем пересечение по прогулкам при условии что расстояние между двумя метками не должно быть больше 10 минут.
     * Если за 10 мину пересечения не было считаем что совместная прогулка завершилась. Сохраняем прогулки которые по времени не менее 10 минут.
     * @param array $lastTrackSession - списко треков инициатора
     * @param array $friendsTrack - списко треков друга
     * @return array
     */
    public function checkWalkInterval($lastTrackSession, $friendsTrack) {

        $minMyTrack = $lastTrackSession[0]->timestamp;
        $maxMyTrack = $lastTrackSession[count($lastTrackSession) - 1]->timestamp;
        $minFriendTrack = $friendsTrack[0]->timestamp;
        $maxFriendTrack = $friendsTrack[count($friendsTrack) - 1]->timestamp;

        $startPeriod = $this->normolizeTimestampToMinutes(min($minMyTrack, $minFriendTrack));
        $endPeriod = $this->normolizeTimestampToMinutes( max($maxMyTrack, $maxFriendTrack));

        $timeLine = [];

        foreach($lastTrackSession as $trackItem){
            $timeLine[$this->normolizeTimestampToMinutes($trackItem->timestamp)][] = $trackItem;
        }

        foreach($friendsTrack as $trackItem){
            $timeLine[$this->normolizeTimestampToMinutes($trackItem->timestamp)][] = $trackItem;
        }

        for ($i = $startPeriod; $i <= $endPeriod; $i+=60) {
            if(!isset($timeLine[$i])){
                $timeLine[$i] = [];
            }
        }

        $startTime = null;
        $endTime = null;
        $iterrator = 0;
        $walkArray = [];
        foreach($timeLine as $time => $tracks) {
            if(count($tracks)){

                if(count($tracks) > 1) {
                    $endTime = max($tracks[0]->timestamp, $tracks[1]->timestamp);
                } else {
                    $endTime = $tracks[0]->timestamp;
                }

            }
            if(count($tracks) && is_null($startTime)){

                if(count($tracks) > 1) {
                    $startTime = min($tracks[0]->timestamp, $tracks[1]->timestamp);
                } else {
                    $startTime = $tracks[0]->timestamp;
                }

                $iterrator = 0;
                continue;
            }
            if(!count($tracks) && !is_null($startTime)){
                if($iterrator < 10){
                    $iterrator++;
                }else{
                    if($endTime - $startTime > 10 * 60){
                        $walkArray[] = [
                            'start' => $startTime,
                            'end' => $endTime
                        ];
                    }
                    $startTime = null;
                    $endTime = null;
                    $iterrator = 0;
                }
            }
        }
        return $walkArray;
    }

    /**
     * По одному трековому сообщению выбираем все сообщения с момента последнего сна и проверяем эту сессию на пересечение с другими устройствами
     * @param array $lastTrackSession - списко треков инициатора
     * @param array $friendsTrack - списко треков друга
     * @return array
     */
    public function checkJoinWalk($dataTrack)
    {
        /** Получаем список треков с момента последнего сна */
        $lastTrackSession = $this->getLastTrackSessions($dataTrack->device_id, $dataTrack->timestamp);

        $joinTrack = DataTrack::find();

        /** Определяем для каждого трека условия поиска других устрйоств в каждый омент времени. */
        foreach ($lastTrackSession as $dataTrackItem) {
            $coordinatesDimension = $this->coordinatesByDistance($dataTrackItem->lat, $dataTrackItem->lon, $dataTrackItem->z, 6, $dataTrackItem->accu);
            $joinTrack->orWhere([
                'and',
                ['>=', 'lat', $coordinatesDimension['xMin']],
                ['<=', 'lat', $coordinatesDimension['xMax']],
                ['>=', 'lon', $coordinatesDimension['yMax']],
                ['<=', 'lat', $coordinatesDimension['yMax']],
                ['>=', 'timestamp', $dataTrackItem->timestamp - 10 * 60],
                ['<=', 'timestamp', $dataTrackItem->timestamp + 10 * 60],
            ]);
        }

        $friendsTracks = $joinTrack
            ->orderBy(['timestamp' => SORT_ASC]);

        $friends = [];

        /** Определяем кондидатов на пересечние */
        foreach ($friendsTracks as $friendsTrack) {
            $friends[$friendsTrack->deviceId][] = $friendsTrack;
        }

        $joinWalks = [];

        foreach ($friends as $friendId => $friendsTrack) {

            /** Сопоставляем сессии инициатора и кондидата и выбираем все пересекающиеся интервалы */
            foreach($this->checkWalkInterval($lastTrackSession, $friendsTrack) as $timeInterval){
                $timeInterval['deviceId'] = $dataTrack->device_id;
                $timeInterval['friendId'] = $friendId;
                $joinWalks[] = new joinWalk($timeInterval);
            }
        }

        foreach ($joinWalks as $joinWalk) {
            $joinWalk->save();
        }
    }
}
