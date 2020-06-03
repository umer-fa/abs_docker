function xhrCall(to, data, options, callback) {
    let defaultOptions = {
        cache: false,
        type: "get",
        dataType: "json"
    };

    // Check if options param was passed
    if (typeof(options) === "object") {
        $.extend(defaultOptions, options); // Merge
    }

    if (typeof(data) !== "object") {
        console.log("xhrCall expects object for second parameter");
        return false;
    }

    // Set url prop
    defaultOptions["url"] = to;
    defaultOptions["data"] = data;

    // Ajax
    $.ajax(defaultOptions).done(function (res) {
        if (typeof(callback) === "function") {
            if (defaultOptions["dataType"] === "json") {
                if (typeof(res) === "object") {
                    callback(res);
                } else {
                    console.log("Expecting an object from XHR")
                }
            } else {
                callback(res);
            }
        }
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("XHR Failed: " + textStatus);
        console.log(errorThrown);
    });
}

function xhrForm(form, callback) {
    if ($(form).is("form")) {
        var to = $(form).attr("action");
        $(form).find(":submit").attr("disabled", true);
        xhrCall(to, $(form).serializeArray(), {type: "post"}, callback);
        $(form).find(":submit").attr("disabled", false);
    }
}

function xhrFormData(form, callback) {
    if ($(form).is("form")) {
        var to = $(form).attr("action");
        $(form).find(":submit").attr("disabled", true);
        xhrCall(to, new FormData($(form)[0]), {type: "post", processData: false, contentType: false}, callback);
        $(form).find(":submit").attr("disabled", false);
    }
}

function processFormResult(form, result) {
    if ($(form).is("form")) {
        if (typeof(result) === "object") {
            // Adding "has-error" class to field
            if (result.hasOwnProperty("param")) {
                $(form).find(':input[name="' + result["param"] + '"]')
                    .addClass("is-invalid");
            }

            // Issue noty
            xhrResult(result);
        }
    }
}

function xhrMessageNotyType(type) {
    if (typeof type === "string") {
        switch (type) {
            case "error":
            case "danger":
                return "error";
            case "success":
            case "info":
            case "warning":
                return type;
            default:
                return "alert";
        }
    }
}

function xhrResult(result) {
    if (typeof(result) === "object") {
        if (result.hasOwnProperty("messages")) {
            var messages = result["messages"];
            if ($.isArray(messages)) {
                $(messages).each(function (num, message) {
                    if (typeof message === "object") {
                        if (message.hasOwnProperty("type") && message.hasOwnProperty("message")) {
                            var notyType = xhrMessageNotyType(message["type"]);
                            var notyMessage = message["message"];
                            var notyLayout = "bottomRight";
                            if (notyType === "success") {
                                notyLayout = "bottom"
                            }

                            issueNoty3(notyType, notyMessage, notyLayout)
                        }
                    }
                });
            }
        }
    }
}

function resetForm(form) {
    if ($(form).is("form")) {
        $(form).find(":input")
            .removeClass("is-invalid");
    }
}

function issueNoty3(type, text, pos, params) {
    if (pos == null) pos = "bottom";
    var notyParams = {
        type: type,
        layout: pos,
        theme: "bootstrap-v4",
        text: text,
        timeout: 3500,
        progressBar: true,
        animation: {
            open: 'animated fadeInRight',
            close: 'animated fadeOutRight'
        }
    };

    if (typeof(params) === "object") {
        $.extend(notyParams, params); // Merge
    }

    new Noty(notyParams).show();
}

function regularXhrForm(form, callback) {
    if ($(form).is("form")) {
        resetForm(form);
        xhrForm(form, function (result) {
            processFormResult(form, result);
            if (typeof(callback) === "function") {
                callback(result);
            }
        });
    }
}
