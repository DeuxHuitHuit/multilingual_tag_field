(function ($, Symphony, window, undefined) {

	function init() {
		$('.field-multilingualtag').each(function () {
			var field = new MultilingualField($(this));
		});
	}
	
	// wait for DOM to be ready
	$(function () {
		var base = Symphony.WEBSITE + '/extensions/multilingual_tag_field/assets/';
		if (typeof this.MultilingualField !== 'function') {
			$.getScript(base + 'multilingual_tag_field.multilingual-field.js', function () {
				init();
			});
		}
		else {
			init();
		}
	});
}(this.jQuery, this.Symphony, this));