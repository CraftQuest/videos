function ServicesListCtrl(e,t,n,r,i,s,o){if(!once){r.$on("$routeChangeSuccess",function(n,i){r.serviceKey=e.serviceKey=t.serviceKey;r.methodName=t.methodName;console.log(r.serviceKey)});once=!0}e.playlistId=t.playlistId;typeof r.services!="undefined"&&(e.currentService=r.services[r.serviceKey]);if(r.serviceKey&&r.methodName){if(r.methodName=="search"){r.search(!0);return}var u={method:r.methodName,service:r.serviceKey,page:1,perPage:Dukt_videos.pagination_per_page};$(".dv-main .toolbar .spinner").removeClass("hidden");n({method:"POST",url:Craft.getActionUrl("duktvideos/ajax/angular",u),cache:!1}).success(function(e,t,n,i){r.videos=e;e.length<Dukt_videos.pagination_per_page?$(".dv-video-more").css("display","none"):$(".dv-video-more").css("display","block");$(".dv-main .toolbar .spinner").addClass("hidden")}).error(function(e,t,n,r){console.log("error",e,t,n,r)})}r.serviceChange=function(){i.path($(".dv-sidebar select").val()+"/"+r.methodName)};e.getClass=function(e){var t=new RegExp("/.*/"+e),n=i.path().match(t);return n?"active":""};var a=!1;r.search=function(e){var t=this.searchQuery,s=new RegExp("/.*/search"),o=i.path().match(s);o||i.path(r.serviceKey+"/search");typeof e=="undefined"&&(e=!1);if(t!=""||e==1){clearTimeout(a);a=setTimeout(function(){console.log("search",t);var e={method:"search",service:r.serviceKey,searchQuery:t,page:1,perPage:Dukt_videos.pagination_per_page};$(".dv-main .toolbar .spinner").removeClass("hidden");n({method:"POST",url:Craft.getActionUrl("duktvideos/ajax/angular",e),cache:!0}).success(function(e,t,n,i){r.videos=e;r.videos.length==0;e.length<Dukt_videos.pagination_per_page?$(".dv-video-more").css("display","none"):$(".dv-video-more").css("display","block");$(".dv-main .toolbar .spinner").addClass("hidden")}).error(function(e,t,n,r){console.log("error",e,t,n,r)})},500)}};e.play=function(t){$("#player").css("visibility","visible");$("#player-overlay").css("visibility","visible");e.selected=t;n({method:"POST",url:Craft.getActionUrl("duktvideos/ajax/angular",{method:"embed",videoUrl:t.url,service:e.serviceKey})}).success(function(e,t,n,r){console.log("--success",$.parseJSON(e));$("#player #videoDiv").html($.parseJSON(e))}).error(function(e,t,n,r){console.log("--error",e,t,n,r)});console.log("play video",t.id)};e.isSelected=function(t){return e.selected===t};e.moreVideos=function(){var e=r.videos.length;console.log("offset",e);$(".dv-video-more").css("display","none");perPage=Dukt_videos.pagination_per_page;page=Math.floor(e/perPage)+1;console.log("page",page);console.log("perPage",perPage);var t={method:r.methodName,service:r.serviceKey,searchQuery:o.searchQuery,page:page,perPage:perPage};n({method:"POST",url:Craft.getActionUrl("duktvideos/ajax/angular",t),cache:!0}).success(function(e,t,n,i){console.log("xxxxsuccess",e);console.log("-----success",e.length);$.merge(r.videos,e);r.videos.length==0;console.log("success",e.length);if(e.length<Dukt_videos.pagination_per_page){console.log("display none");$(".dv-video-more").css("display","none")}else $(".dv-video-more").css("display","block");$(".dv-main .toolbar .spinner").addClass("hidden")}).error(function(e,t,n,r){console.log("error",e,t,n,r)})}}var once=!1;