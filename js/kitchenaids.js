$.fn.serializeObject = function()
{
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};
(function($) {
    "use strict";
    $.kitchenaids = {
        options: {},
        init: function(options) {
            var that = this;
            that.options = options;
            this.initMain();

        },
        initMain: function() {
            $('.plugins-settings-form').submit(function() {
                var form = $(this);
                var url = form.attr('action');
                var processId;
                //var pull = [];

                form.find('.s-regenerate-progressbar').show();
                form.find('.progressbar .progressbar-inner').css('width', '0%');
                form.find('.progressbar-description').text('0.000%');
                form.find('.progressbar').show();
                form.find(".s-regenerate-report").hide();

                var cleanup = function() {
                    $.post(url, {processId: processId, cleanup: 1}, function(r) {
                        // show statistic
                        form.find('.s-regenerate-progressbar').hide();
                        form.find(".s-regenerate-report").show();
                        if (r.report) {
                            form.find(".s-regenerate-report").html(r.report);
                            form.find(".s-regenerate-report").find('.close').click(function() {
                                //dialog.trigger('close');
                            });
                        }
                        //dialog.removeClass('height400px').addClass('height350px');
                    }, 'json');
                };

                var step = function(delay) {
                    delay = delay || 2000;
                    var timer_id = setTimeout(function() {
                        $.post(url, $.extend({processId: processId},form.serializeObject()),
                        function(r) {
                            if (!r) {
                                step(3000);
                            } else if (r && r.ready) {
                                form.find('.progressbar .progressbar-inner').css({
                                    width: '100%'
                                });
                                form.find('.progressbar-description').text('100%');
                                cleanup();
                            } else if (r && r.error) {
                                form.find('.errormsg').text(r.error);
                            } else {
                                if (r && r.progress) {
                                    var progress = parseFloat(r.progress.replace(/,/, '.'));
                                    form.find('.progressbar .progressbar-inner').animate({
                                        'width': progress + '%'
                                    });
                                    form.find('.progressbar-description').text(r.progress + ' - ' + r.step);
                                }
                                if (r && r.warning) {
                                    form.find('.progressbar-description').append('<i class="icon16 exclamation"></i><p>' + r.warning + '</p>');
                                }
                                step();
                            }
                        },
                                'json').error(function() {
                            step(3000);
                        });
                    }, delay);
                    //pull.push(timer_id);
                };

                $.post(url, form.serializeArray(),
                        function(r) {
                            if (r && r.processId) {
                                processId = r.processId;
                                step(1000);   // invoke Runner
                                step();         // invoke Messenger
                            } else if (r && r.error) {
                                form.find('errormsg').text(r.error);
                            } else {
                                form.find('errormsg').text('Server error');
                            }
                        },
                        'json').error(function() {
                    form.find('errormsg').text('Server error');
                });

                return false;
            });

        }
    };

})(jQuery);