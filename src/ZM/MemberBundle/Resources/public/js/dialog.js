!
function(a, b) {
    "function" == typeof define && define.amd ? define(["$"], b) : "object" == typeof exports ? module.exports = b() : a.Dialog = b(window.Zepto || window.jQuery || $)
} (this,
function(a) {
    a.fn.Dialog = function(c) {
        var d = [];
        return a(this).each(function() {
            var e = new b,
            f = a.extend({
                trigger: a(this)
            },
            c);
            e.init(f),
            d.push(e)
        }),
        d
    },
    a.Dialog = function(c) {
        if ("alert" === c.type) {
            var d = new b,
            f = "";
            c.button ? ("boolean" == typeof c.button && (c.button = "确定"), f = '<p class="ui-dialog-action"><button class="ui-alert-submit  js-dialog-close">' + c.button + "</button></p>") : c.timer || (c.timer = 3000),
            e += f;
            var g = a.extend({
                target: e,
                animate: !0,
                show: !0,
                mask: !0,
                className: "ui-alert",
                afterHide: function() {
                    this.dispose(),
                    c.callback && c.callback()
                }
            },
            c);
            d.init(g),
            c.timer && setTimeout(function() {
                d.dispose(),
                c.callback && c.callback()
            },
            c.timer),
            d.touch(d.mask,
            function() {
                d.dispose(),
                c.callback && c.callback()
            })
        }
        if ("confirm" === c.type) {
            var h = new b,
            e = '<div class="ui-confirm-title">' + c.content + "</div>",
            f = "";
            c.buttons || (c.buttons = [{
                yes: "确定"
            }]);
            for (var i = "",
            j = 0,
            k = c.buttons.length; k > j; j++) {
                var l = c.buttons[j];
                l.yes && (i += '<td><button class="ui-confirm-submit " data-type="yes">' + l.yes + "</button></td>"),
                l.close && (i += '<td><button class="ui-confirm-close js-dialog-close" data-type="close">' + l.close + "</button></td>")
            }
            f = '<table class="ui-dialog-action"><tr>' + i + "</tr></table>",
            e += f;
            var m = a.extend({
                target: e,
                animate: !0,
                show: !0,
                mask: !0,
                className: "ui-alert",
                afterHide: function() {
                    this.dispose()
                },
                beforeShow: function(b) {
                    h.touch(a(".ui-confirm-submit", b),
                    function() {
                        c.callback && c.callback.call(h, "yes", b)
                    })
                }
            },
            c);
            h.init(m)
        }
    },
    a.alert = function(b, c, d, e) {
        a.Dialog({
            content: b,
            button: c,
            timer: e,
            callback: d,
            zIndex: 100,
            type: "alert"
        })
    },
    a.confirm = function(b, c, d, e) {
        a.Dialog(a.extend({
            content: b,
            buttons: c,
            callback: d,
            zIndex: 100,
            type: "confirm"
        },
        e))
    };
    var b = function() {
        var b = Math.random().toString().replace(".", "");
        this.id = "dialog_" + b,
        this.settings = {},
        this.settings.titleTpl = a('<div class="ui-dialog-title"></div>'),
        this.timer = null,
        this.showed = !1,
        this.mask = a()
    };
    return b.prototype = {
        init: function(b) {
            this.settings = a.extend({},
            this.settings, b),
            this.settings.mask && (this.mask = a('<div class="ui-dialog-mask"/>'), a("body").append(this.mask)),
            a("body").append('<div class="ui-dialog" id="' + this.id + '"></div>'),
            this.dialogContainer = a("#" + this.id);
            var c = this.settings.zIndex || 10;
            this.dialogContainer.css({
                zIndex: c
            }),
            this.settings.className && this.dialogContainer.addClass(this.settings.className),
            this.mask.css({
                zIndex: c - 1
            }),
            this.settings.closeTpl && this.dialogContainer.append(this.settings.closeTpl),
            this.settings.title && (this.dialogContainer.append(this.settings.titleTpl), this.settings.titleTpl.html(this.settings.title)),
            this.bindEvent(),
            this.settings.show && this.show()
        },
        touch: function(b, c) {
            function d(a) {
                return c.call(this, a)
            }
            var e;
            a(b).on("click", d),
            a(b).on("touchmove",
            function() {
                e = !0
            }).on("touchend",
            function(a) {
                if (a.preventDefault(), !e) {
                    var b = c.call(this, a, "touch");
                    b || (a.preventDefault(), a.stopPropagation())
                }
                e = !1
            })
        },
        bindEvent: function() {
            var b = this;
            this.settings.trigger && (a(this.settings.trigger).click(function() {
                b.show()
            }), b.touch(a(this.settings.trigger),
            function() {
                b.show()
            })),
            a(window).resize(function() {
                b.setPosition()
            }),
            a(window).scroll(function() {
                b.setPosition()
            }),
            a(window).keydown(function(a) {
                27 === a.keyCode && b.showed && b.hide()
            })
        },
        dispose: function() {
            this.dialogContainer.remove(),
            this.mask.remove(),
            this.timer && clearInterval(this.timer)
        },
        hide: function() {
            var b = this;
            b.settings.beforeHide && b.settings.beforeHide.call(b, b.dialogContainer),
            this.showed = !1,
            this.mask.hide(),
            this.timer && clearInterval(this.timer),
            this.settings.animate ? (this.dialogContainer.removeClass("zoomIn").addClass("zoomOut"), setTimeout(function() {
                b.dialogContainer.hide(),
                "object" == typeof b.settings.target && a("body").append(b.dialogContainer.hide()),
                b.settings.afterHide && b.settings.afterHide.call(b, b.dialogContainer)
            },
            500)) : (this.dialogContainer.hide(), "object" == typeof this.settings.target && a("body").append(this.dialogContainer), this.settings.afterHide && this.settings.afterHide.call(this, this.dialogContainer))
        },
        show: function() {
            this.dailogContent = "string" == typeof this.settings.target ? a(/^(\.|\#\w+)/gi.test(this.settings.target) ? this.settings.target: "<div>" + this.settings.target + "</div>") : this.settings.target,
            this.mask.show(),
            this.dailogContent.show(),
            this.height = this.settings.height || "auto",
            this.width = this.settings.width || "auto",
            this.dialogContainer.append(this.dailogContent).show().css({
                height: this.height,
                width: this.width
            }),
            this.settings.beforeShow && this.settings.beforeShow.call(this, this.dialogContainer),
            this.showed = !0,
            a(this.settings.trigger).blur(),
            this.setPosition();
            var b = this;
            this.timer && clearInterval(this.timer),
            this.timer = setInterval(function() {
                b.setPosition()
            },
            1000),
            this.settings.animate && this.dialogContainer.addClass("zoomIn").removeClass("zoomOut").addClass("animated")
        },
        setPosition: function() {
            if (this.showed) {
                var a = this;
                this.dialogContainer.show(),
                this.height = this.settings.height,
                this.width = this.settings.width,
                isNaN(this.height) && (this.height = this.dialogContainer.outerHeight && this.dialogContainer.outerHeight() || this.dialogContainer.height()),
                isNaN(this.width) && (this.width = this.dialogContainer.outerWidth && this.dialogContainer.outerWidth() || this.dialogContainer.width());
                var b = this.settings.clientHeight || document.documentElement.clientHeight || document.body.clientHeight,
                c = this.settings.clientWidth || document.documentElement.clientWidth || document.body.clientWidth,
                d = this.width / 2,
                e = this.height / 2,
                f = c / 2 - d,
                g = b / 2 - e;
                f = Math.max(0, f),
                g = Math.max(0, g),
                a.dialogContainer.css({
                    position: "fixed",
                    top: g,
                    left: f
                })
            }
        }
    },
    b
});