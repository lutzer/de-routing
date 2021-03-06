define([
	'jquery',
	'underscore',
	'views/BaseView'
], function($, _, BaseView){
	
	var BaseListItemView = BaseView.extend({
		
		initialize: function() {
			this.listenTo(this.model, 'destroy', this.close);
			BaseView.prototype.initialize.call(this);
		},
	
	});
	// Our module now returns our view
	return BaseListItemView;
	
});