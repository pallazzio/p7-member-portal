(function($){
	"use strict";
	$(document).ready(function(){
		/********************** Write jQuery/JavaScript Here **********************/

		var ALB  = '.p7mp-login-box';
		var ALBA = '.p7mp-login-box-activator';
		if($(ALBA).length){
			$('body').on('click', ALBA, function(e){
				$(ALB).toggleClass('hidden');
					e.stopPropagation();
			});
			$('body').on('click', function(e){
				if(!$(ALB).has(e.target).length > 0 && !$(ALB).hasClass('hidden')){
					$(ALB).toggleClass('hidden');
					e.stopPropagation();
				}
			});
		}

		$('form[id^=p7mp]').on('submit', function(){
			$(this).append('<div class="p7-mp-submission-processing"></div><div class="p7-mp-submission-processing-spinner"></div>');
		});
	
	});
})(jQuery);
