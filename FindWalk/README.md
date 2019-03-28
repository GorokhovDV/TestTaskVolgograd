```

    /**
     * Метод принимает координаты устройства и возвращает координаты параллелепипеда для дальнейшего поиска с учетом доверенного интервала
     * @param float $x - GPS координата широта
     * @param float $y - GPS координата долгота
     * @param float $z - высота
     * @param int $distance - дистанция относительно которой рассматриваем
     * @param int $fallibility - доверенный интервал в метрах
     * @return array - массив с координатами прямоугольника
     */
    public function coordinatesByDistance(float $x, float $y, int $z, int $distance, int $fallibility)
    {

        //Если позволите я не буду сейчас расписывать механизм перевода координат в прямоугольник, думаю для понимания принципа это не сильно важно

        return [
            'xMin' => (float),
            'xMax' => (float),
            'yMin' => (float),
            'yMax' => (float),
            'zMin' => (int),
            'zMax' => (int),
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

    public function normolizeTimestampToMinutes($timestamp) {
        return  floor($timestamp / 60) * 60;
    }

    public function checkWalkInterval($lastTrackSession, $friendsTrack) {

        $minMyTrack = $lastTrackSession[0]->timestamp;
        $maxMyTrack = $lastTrackSession[count($lastTrackSession) - 1]->timestamp;
        $minFriendTrack = $friendsTrack[0]->timestamp;
        $maxFriendTrack = $friendsTrack[count($friendsTrack) - 1]->timestamp;

        $startPeriod = normolizeTimestampToMinutes(min($minMyTrack, $minFriendTrack));
        $endPeriod = normolizeTimestampToMinutes( max($maxMyTrack, $maxFriendTrack));

        for ($i = $startPeriod; $i <= $endPeriod; $i+=60) {
            
        }

    }


    public function checkJoinWalk($dataTrack)
    {

        $lastTrackSession = getLastTrackSessions($dataTrack->device_id, $dataTrack->timestamp);

        $joinTrack = DataTrack::find();

        foreach ($lastTrackSession as $dataTrackItem) {
            $coordinatesDimension = coordinatesByDistance($dataTrackItem->lat, $dataTrackItem->lon, $dataTrackItem->z, 6, $dataTrackItem->accu);
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

        foreach ($friendsTracks as $friendsTrack) {
            $friends[$friendsTrack->deviceId][] = $friendsTrack;
        }

        $joinWalks = [];

        foreach ($friends as $friendId => $friendsTrack) {
            $joinWalks = array_merge($joinWalks, checkWalkInterval($lastTrackSession, $friendsTrack));
        }

        foreach ($joinWalks as $joinWalk) {
            $joinWalk->save();
        }
    }
    
```