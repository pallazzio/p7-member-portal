(function($){
	"use strict";
	$(document).ready(function(){
		/********************** Write jQuery/JavaScript Here **********************/

		var plzmpALB  = '.plzmp-login-box';
		var plzmpALBA = '.plzmp-login-box-activator';
		if($(plzmpALBA).length){
			$('body').on('click', plzmpALBA, function(e){
				$(plzmpALB).toggleClass('hidden');
					e.stopPropagation();
			});
			$('body').on('click', function(e){
				if(!$(plzmpALB).has(e.target).length > 0 && !$(plzmpALB).hasClass('hidden')){
					$(plzmpALB).toggleClass('hidden');
					e.stopPropagation();
				}
			});
		}
	
	});
})(jQuery);
