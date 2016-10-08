    function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                alert("浏览器不支持地理定位。");
            }
        }
        function showPosition(position) {
            var latlon = position.coords.latitude+','+position.coords.longitude;
            var latitude = position.coords.latitude;
            var longitude= position.coords.longitude;
            //google
            var url = 'http://maps.google.cn/maps/api/geocode/json?latlng=' + latlon + '&language=CN';
            $.ajax({
                type: "GET",
                url: url,
                success: function (json) {
                    if (json.status == 'OK') {
                        var result = json.results;
                        // console.log(result);
                        $.ajax({
                            type: "post",
                            url: "{{ path('zm_member_setLocationAjax') }}",
                            data: {
                                latitude: latitude,
                                longitude: longitude,
                                location_json:result
                            },
                            success: function (result) {
                                 
                                confirm(result.content);
                               
                            } 
                        });
                    }
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    alert("地址位置获取失败");
                    //$("#google_geo").html(latlon + "地址位置获取失败");
                }
            });
        }

        function showError(error) {
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    alert("定位失败,用户拒绝请求地理定位");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("定位失败,位置信息是不可用");
                    break;
                case error.TIMEOUT:
                    alert("定位失败,请求获取用户位置超时");
                    break;
                case error.UNKNOWN_ERROR:
                    alert("定位失败,定位系统失效");
                    break;
            }
        }

        getLocation();