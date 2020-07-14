$(document).ready(function () {
    $(':input[autocomplete="off"]').val("");
    $(':input[data-onload-value]').each(function () {
        $(this).val($(this).attr("data-onload-value"));
    });
});

let root = window.location.protocol + "//" + window.location.hostname +
    (window.location.port ? ":" + window.location.port : "");

let authToken = window.location.pathname;
authToken = authToken[0] === "/" ? authToken.substr(1) : authToken;
authToken = authToken.split("/");
authToken = authToken[0];

let rootPath = root + "/" + authToken;

String.prototype.ucfirst = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();

    $(".xhr-form").on("submit", function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).is("form")) {
            let form = this;
            regularXhrForm(form, function (result) {
                if (result.hasOwnProperty("status")) {
                    if (result["status"] === true) {
                        $(form).find(':input[data-success-reset]').val("");
                    }
                }

                if (result.hasOwnProperty("reset")) {
                    if (typeof result["reset"] === "string") {
                        $(form).find(':input[name="' + result["reset"] + '"]').val("");
                    } else {
                        $(form)[0].reset();
                    }
                }

                if (result.hasOwnProperty("destruct") || result.hasOwnProperty("disabled")) {
                    $(form).attr("src", "#");
                    $(form).find(":input").prop("disabled", true);
                    $(form).find(":submit").prop("disabled", true);
                }
            });
        }
    });

    $("input.input-int").on("change keyup blur", function () {
        let current = $(this).val();
        $(this).val(current.replace(/[^0-9]/g, ''));
    });

    $("input.input-float").on("change keyup blur", function () {
        let scale = parseInt($(this).attr("data-scale"));
        if (isNaN(scale)) {
            scale = 8;
        }

        // Current value
        let current = $(this).val();

        // Remove unwanted digits
        current = current.replace(/[^0-9.]/g, '');

        // Should round?
        if (current.match(/^[0-9]+\.[1-9]+[0-9]*$/)) {
            let rounded = round(current, scale);
            if (isNaN(rounded)) {
                rounded = current.split(".")[0];
            }

            current = rounded;
        }

        $(this).val(current);
    });

    $("input.input-uc").on("change keyup blur", function () {
        let value = $(this).val();
        if (value.length) {
            $(this).val(value.toUpperCase());
        }
    });

    $("input.input-lc").on("change keyup blur", function () {
        let value = $(this).val();
        if (value.length) {
            $(this).val(value.toLowerCase());
        }
    });
});

function cleanDecimalDigits(amount) {
    if (amount.length) {
        if (amount.indexOf(".") >= 1) {
            amount = amount.replace(/0+$/, '');
            amount = amount.replace(/\.+$/, '');

            return amount;
        }
    }

    return amount;
}

function round(value, decimals) {
    return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
}

$(function () {
    $.fn.serializeObject = function () {
        let o = {};
        let a = this.serializeArray();
        $.each(a, function () {
            if (o[this.name]) {
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
});
