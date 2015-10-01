"undefined"==typeof Videos&&(Videos={}),Videos.Field=Garnish.Base.extend({$input:null,$container:null,$spinner:null,$preview:null,$player:null,$playBtn:null,$addBtn:null,$removeBtn:null,explorer:null,playerModal:null,videoSelectorModal:null,explorerHtml:null,init:function(e,i){this.$input=$("#"+e),this.$container=this.$input.parents(".videos-field"),this.$spinner=$(".spinner",this.$container),this.$preview=$(".preview",this.$container),this.$playBtn=$(".play",this.$container),this.$addBtn=$(".videos-add",this.$container),this.$removeBtn=$(".delete",this.$container),this.addListener(this.$input,"textchange","fieldPreview"),this.addListener(this.$playBtn,"click","playVideo"),this.addListener(this.$addBtn,"click","addVideo"),this.addListener(this.$removeBtn,"click","removeVideo"),"undefined"!=typeof i.explorerHtml&&(this.explorerHtml=i.explorerHtml)},removeVideo:function(e){this.$input.val(""),this.fieldPreview(),e.preventDefault()},addVideo:function(e){this.videoSelectorModal?this.videoSelectorModal.show():($videoSelectorModal=$('<div class="videoselectormodal modal"></div>').appendTo(Garnish.$bod),$wrap=$('<div class="wrap"/>').appendTo($videoSelectorModal),$footer=$('<div class="footer"/>').appendTo($videoSelectorModal),$buttons=$('<div class="buttons right"/>').appendTo($footer),$cancelBtn=$('<div class="btn">'+Craft.t("Cancel")+"</div>").appendTo($buttons),$selectBtn=$('<input type="submit" class="btn submit disabled" value="'+Craft.t("Select")+'" />').appendTo($buttons),this.videoSelectorModal=new Garnish.Modal($videoSelectorModal,{visible:!1,resizable:!1}),this.addListener($cancelBtn,"click",function(){this.videoSelectorModal.hide()}),this.addListener($selectBtn,"click",function(){this.$input.val(url),this.$input.trigger("change"),this.videoSelectorModal.hide()}),this.explorerHtml&&($wrap.html(this.explorerHtml),this.explorer=new Videos.Explorer($videoSelectorModal,{onPlayerHide:$.proxy(function(){this.videoSelectorModal.show()},this),onSelectVideo:$.proxy(function(e){$selectBtn.removeClass("disabled")},this),onDeselectVideo:$.proxy(function(){$selectBtn.addClass("disabled")},this)}),this.videoSelectorModal.updateSizeAndPosition(),Craft.initUiElements()))},playVideo:function(e){var i=$(e.currentTarget).data("gateway"),t=$(e.currentTarget).data("id");this.playerModal?this.playerModal.show():this.playerModal=new Videos.Player,this.playerModal.play({gateway:i,videoId:t})},fieldPreview:function(){var e=this.$input.val();e?(this.$spinner.removeClass("hidden"),$(".error",this.$container).addClass("hidden"),Craft.postActionRequest("videos/fieldPreview",{url:e},$.proxy(function(e,i){this.$spinner.addClass("hidden"),this.$preview.show(),"success"==i&&(e.error?this.$preview.html('<p class="error">'+e.error+"</p>"):(this.$preview.html(e.preview),$playBtn=$(".play",this.$container),this.addListener($playBtn,"click","playVideo")))},this))):this.$preview.hide()}}),$(document).ready(function(){"undefined"!=typeof Matrix&&Matrix.bind("dukt_videos","display",function(e){var i=$(".input",this);if(i.length){var t=e.field.id+"["+e.row.id+"]["+e.col.id+"]",l=t.replace(/[^\w\-]+/g,"_");i.attr("id",l),e.videosField=new Videos.Field(l)}})});