(function($, monetize_media) {
    monetize_media = $.extend(monetize_media || {}, {
        insert_at_caret: function(el, myValue) {
            if (document.selection) {

            el.focus();
            sel = document.selection.createRange();
            sel.text = myValue;
            el.focus();

            } else if (el.selectionStart || el.selectionStart == '0') {
                var startPos = el.selectionStart;
                var endPos = el.selectionEnd;
                var scrollTop = el.scrollTop;
                el.value = el.value.substring(0, startPos)+ myValue+ el.value.substring(endPos,el.value.length);
                el.focus();
                el.selectionStart = startPos + myValue.length;
                el.selectionEnd = startPos + myValue.length;
                el.scrollTop = scrollTop;
            } else {
                el.value += myValue;
                el.focus();
            }
        },
        process_image: function(attachment) {
            var a = {
                alt: attachment.alt || '',
                caption: attachment.caption || '',
                title: attachment.title || '',
                linkUrl: attachment.linkUrl || '', // Link url
                url: attachment.url || ''    // File url
            };

            var r = ['<img src="'+a.url+'" alt="'+(a.alt || a.caption)+'"/>'];

            if(a.linkUrl){
                r.unshift('<a href="'+a.linkUrl+'" title="'+(a.title || a.alt)+'">');
                r.push('</a>');
            }

            return r;
        },
        process_swf: function(attachment) {
            var a = {
                linkUrl: attachment.linkUrl || '',  // Link to url
                url: attachment.url || ''    // File url
            }, ret = [];



            if(a.linkUrl) {
                ret.push('<div class="monetize-flash" data-monetize-movie="'+a.url+
                        '" data-monetize-clicktag="'+a.linkUrl+'&clickTarget=_self">');
            } else {
                 ret.push('<div class="monetize-flash" data-monetize-movie="'+a.url+'">');
            }

            ret.push(
                '<a href="http://www.adobe.com/go/getflashplayer">',
                '<img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" />',
                '</a>',
                '</div>'
            );

            return ret;
        },
        process_link: function(attachment) {
            var a = {
                title: attachment.title || '',
                linkUrl: attachment.linkUrl || '',  // Link url
                url: attachment.url || ''    // File url
            };

            return [
                '<a href="'+(a.linkUrl || a.url)+'">',
                a.title,
                '</a>'
            ];
        },
        to_editor: function(el, insert) {
            monetize_media.insert_at_caret(el, insert.join('\n'));
        }
    });

    $(document).ready(function($){
        // Prepare the variable that holds our custom media manager.
        var monetize_media_frame;

        // Bind to our click event in order to open up the new media experience.
        $(document.body).on('click', '.monetize-unit-media-open', function(e){
            // Prevent the default action from occuring.
            e.preventDefault();

            // If the frame already exists, re-open it.
            if ( monetize_media_frame ) {
                monetize_media_frame.open();
                return;
            }

            // New frame
            monetize_media_frame = wp.media.frames.monetize_media_frame = wp.media({
                className: 'media-frame monetize-unit-media-frame',
                frame: 'post',
                multiple: false
            });

            // Modify insert from URL screen
            var _Embed = wp.media.view.Embed;
            wp.media.view.Embed = _Embed.extend({
                refresh: function() {
                    _Embed.prototype.refresh.apply(this, arguments);

                    // Hide align div
                    this.$el.find('div.align').remove();

                    // Hardcode align to none
                    this.model.set('align', 'none');

                    // Remove all link options except Custom URL
                    this.$el.find(
                        'button[value="file"], button[value="none"]'
                    ).remove();

                    // Trigger click on Custom URL button
                    this.$el.find('button[value="custom"]').trigger('click');

                    // Hardcode link to custom
                    this.model.set('link', 'custom');
                }
            });

            // Modify attachment display settings screen
            var _AttachmentDisplay = wp.media.view.Settings.AttachmentDisplay;
            wp.media.view.Settings.AttachmentDisplay = _AttachmentDisplay.extend({
                render: function() {
                    _AttachmentDisplay.prototype.render.apply(this, arguments);
                    // Hide alignment
                    this.$el.find('select.alignment').parent().remove();
                    // Hardcode to none
                    this.model.set('align', 'none');

                    // Hide size
                    this.$el.find('select.size').parent().remove();
                    // Hardcode to full
                    this.model.set('size', 'full');

                    // Remove all link to options
                    this.$el.find('select.link-to').children().remove();
                
                    // Add our own link to
                    this.$el.find('select.link-to')
                        .append($('<option>', {
                            value: 'custom',
                            text: monetize_media.i18n.custom_url
                        }));

                    // Hardcode to custom
                    this.model.set('link', 'custom');

                    return this;
                }
            });

            // Capture embed view, select event
            monetize_media_frame.state('embed').on('select', function() {
                var state = monetize_media_frame.state(),
                type = state.get('type'),
                embed = state.props.toJSON(),
                insert = [];

                switch (type) {
                    case 'image':
                        insert = monetize_media.process_image(embed);
                        break;
                    case 'link':
                    default:
                        insert = monetize_media.process_link(embed);
                        break;
                }

                monetize_media.to_editor($('#monetize-unit-html')[0], insert);
            });

            // Capture insert view, insert event
            monetize_media_frame.state('insert').on('insert', function(){
                var state = monetize_media_frame.state(),
                selection = state.get('selection').first(),
                insert = [];

                // Merge attachment details and attachment display settings
                var attachment = $.extend(
                        selection.toJSON(),  // Attachment details,
                        state.display(selection).toJSON()  // Attachment display settings
                );

                if(attachment.type === 'image') {
                    insert = monetize_media.process_image(attachment);
                } else if(attachment.type === 'application' &&
                        attachment.subtype === 'x-shockwave-flash'){
                    insert = monetize_media.process_swf(attachment);
                } else {
                     insert = monetize_media.process_link(attachment);
                }

                monetize_media.to_editor($('#monetize-unit-html')[0], insert);
            });

            // Open up the frame.
            monetize_media_frame.open();
        });
    });
})(jQuery, monetize_media);