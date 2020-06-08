let errorsContainer = $("div#errors-reporter");
let totpModal = $("div#totpModal");

function xhrCall(to, data, options, callback) {
    let defaultOptions = {
        cache: false,
        type: "get",
        dataType: "json"
    };

    // Check if options param was passed
    if (typeof (options) === "object") {
        $.extend(defaultOptions, options); // Merge
    }

    if (typeof (data) !== "object") {
        console.log("xhrCall expects object for second parameter");
        return false;
    }

    // Set url prop
    defaultOptions["url"] = to;
    defaultOptions["data"] = data;

    // Ajax
    $.ajax(defaultOptions).done(function (res) {
        if (typeof (callback) === "function") {
            if (defaultOptions["dataType"] === "json") {
                if (typeof (res) === "object") {
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
        let to = $(form).attr("action");
        $(form).find(":submit").attr("disabled", true);
        xhrCall(to, $(form).serializeArray(), {type: "post"}, callback);
        $(form).find(":submit").attr("disabled", false);
    }
}

function processFormResult(form, result) {
    if ($(form).is("form")) {
        if (typeof (result) === "object") {
            if (result.hasOwnProperty("messages")) {
                let messages = result["messages"];
                if ($.isArray(messages)) {
                    // Form fields messages
                    $(messages).each(function (num, message) {
                        if (typeof message === "object") {
                            let param = message["param"];
                            if (typeof param === "string" && param.length) {
                                let elem = $(form).find(':input[name="' + param + '"]');
                                if (elem && elem.length) {
                                    $(elem).addClass("is-invalid");

                                    let paramMessage = message["message"];
                                    if (paramMessage.length) {
                                        let paramMessageElem = $("<div></div>");
                                        $(paramMessageElem).addClass("invalid-feedback animated fadeInUp");
                                        $(paramMessageElem).text(paramMessage);
                                        $(paramMessageElem).insertAfter(elem);
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Adding "has-error" class to field
            if (result.hasOwnProperty("param")) {
                $(form).find(':input[name="' + result["param"] + '"]')
                    .addClass("is-invalid");
            }

            // Issue noty
            xhrResult(result, form);
        }
    }
}

function xhrMessageNotyType(type) {
    if (typeof type === "string") {
        type = type.toLowerCase();
        switch (type) {
            case "notice":
                return "warning";
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

function xhrResult(result, form) {
    let ignoreFormParams = false;
    if (form && form.length) {
        ignoreFormParams = true;
    }

    if (typeof (result) === "object") {
        if (result.hasOwnProperty("messages")) {
            let messages = result["messages"];
            if ($.isArray(messages)) {
                $(messages).each(function (num, message) {
                    if (typeof message === "object") {
                        let msgParam = message["param"];
                        if (msgParam && msgParam.length) {
                            if (ignoreFormParams === true) {
                                return;
                            }
                        }

                        if (message.hasOwnProperty("type") && message.hasOwnProperty("message")) {
                            let notyType = xhrMessageNotyType(message["type"]);
                            let notyMessage = message["message"];
                            let notyLayout = "bottomRight";
                            if (notyType === "success") {
                                notyLayout = "bottom"
                            }

                            issueNoty3(notyType, notyMessage, notyLayout)
                        }
                    }
                });
            }
        }

        if (result.hasOwnProperty("status")) {
            if (result["status"] === false) {
                if (result.hasOwnProperty("totpAuthModal") && result["totpAuthModal"] === true) {
                    $(totpModal).modal('show');
                }
            }

            if (result.hasOwnProperty("totpModalClose")) {
                $(totpModal).modal('hide');
            }
        }

        let hasErrors = false;
        if (result.hasOwnProperty("errors")) {
            let errors = result["errors"];
            if ($.isArray(errors) && errors.length > 0) {
                hasErrors = true;
                if (errorsContainer && errorsContainer.length) {
                    let errorsContainerList = $(errorsContainer).find("#errors-list");
                    $(errors).each(function (i, error) {
                        if (typeof error === "object") {
                            let thisError = $("<div></div>");
                            $(thisError).addClass("alert alert-dismissible fade show alert-" + xhrMessageNotyType(error["typeStr"]));
                            $(thisError).text(error["message"]);
                            $(thisError).append('<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>');
                            $(errorsContainerList).append(thisError);
                        }
                    });

                    $(errorsContainer).slideDown();
                }
            }
        }

        let letChangeLocation = true;
        let changeLocationTrigger = $("<div></div>").addClass("alert alert-warning");
        if (hasErrors === true) {
            if ($(errorsContainer).length) {
                letChangeLocation = false;
            }
        }

        if (result.hasOwnProperty("redirect")) {
            if (letChangeLocation === true) {
                setTimeout(function () {
                    document.location.replace(result["redirect"]);
                }, 1500);
            } else {
                $(changeLocationTrigger).append('To continue, please click <a href="' + result["redirect"] + '"><strong>here</strong></a>');
                $(errorsContainer).find("#errors-list").append(changeLocationTrigger);
                $(errorsContainer).slideDown();
            }
        } else if (result.hasOwnProperty("refresh")) {
            if (letChangeLocation === true) {
                setTimeout(function () {
                    document.location.reload();
                }, 1500);
            } else {
                $(changeLocationTrigger).append('To continue, please click <a href="javascript:document.location.reload();"><strong>here</strong></a>');
                $(errorsContainer).find("#errors-list").append(changeLocationTrigger);
                $(errorsContainer).slideDown();
            }
        }
    }
}

function resetForm(form) {
    if ($(form).is("form")) {
        $(form).find(":input")
            .removeClass("is-invalid");

        $(form).find("div.invalid-feedback").remove();
    }
}

function issueNoty3(type, text, pos, params) {
    if (pos == null) pos = "bottom";
    let notyParams = {
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

    if (typeof (params) === "object") {
        $.extend(notyParams, params); // Merge
    }

    new Noty(notyParams).show();
}

function regularXhrForm(form, callback) {
    if ($(form).is("form")) {
        resetForm(form);
        xhrForm(form, function (result) {
            processFormResult(form, result);
            if (typeof (callback) === "function") {
                callback(result);
            }
        });
    }
}
