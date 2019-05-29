/**
 * jquery.repeater version 1.3.1
 *
 * https://github.com/DubFriend/jquery.repeater
 * (MIT) 09-10-2016
 * Brian Detering <BDeterin@gmail.com> (http://www.briandetering.net/)
 *
 * jquery.input version 0.1.0
 * https://github.com/DubFriend/jquery.input
 * (MIT) 07-06-2018
 * Brian Detering <BDeterin@gmail.com> (http://www.briandetering.net/)
 *
 * Enhancements and fixes by
 *  - @nkchinh (Chinh Nguyen) 2019
 *    https://github.com/nkchinh/jquery.repeater
 *
 * @preserve
 */

(function ($) {
'use strict';

var identity = function (x) {
    return x;
};

var isArray = function (value) {
    return $.isArray(value);
};

var isObject = function (value) {
    return !isArray(value) && (value instanceof Object);
};

var isFunction = function (value) {
    return value instanceof Function;
};

var toInt = function (value) {
    return parseInt(value, 10);
};

var bind = function (f, object) {
    return function () {
        return f.apply(object, arguments);
    };
};

var partial = function (f) {
    var args = Array.prototype.slice.call(arguments, 1);
    if(isFunction(f)) {
        return function () {
            var remainingArgs = Array.prototype.slice.call(arguments);
            return f.apply(null, args.concat(remainingArgs));
        };
    }
};

var argumentsToArray = function (args) {
    var array = [], i;
    for(i = 0; i < args.length; i += 1) {
        array.push(args[i]);
    }
    return array;
};

var isEmpty = function (object) {
    for(var i in object) {
        if(object.hasOwnProperty(i)) {
            return false;
        }
    }
    return true;
};

var isNumeric = function (candidate) {
    return !isNaN(candidate);
};

var isInteger = function (candidate) {
    return isNumeric(candidate) && Number(candidate) % 1 === 0;
};

var indexOf = function (object, value) {
    return $.inArray(value, object);
};

var inArray = function (array, value) {
    return indexOf(array, value) !== -1;
};

//deep copy of json objects
var copy = function (object) {
    return $.extend(true, {}, object);
};

var shallowCopy = function (objects) {
    return map(objects, identity);
};

var foreach = function (collection, callback) {
    for(var i in collection) {
        if(collection.hasOwnProperty(i)) {
            callback(collection[i], i, collection);
        }
    }
};

var range = function (a, b) {
    var i, start, end, array = [];
    if(b === undefined) {
        start = 0;
        end = a - 1;
    }
    else {
        start = a;
        end = b;
    }
    for(i = start; i <= end; i += 1) {
        array.push(i);
    }
    return array;
};

var reverse = function (array) {
    var reversed = [], i;
    for(i = array.length - 1; i >= 0; i -= 1) {
        reversed.push(array[i]);
    }
    return reversed;
};

var last = function (array) {
    return array[array.length - 1];
};

var mapToArray = function (collection, callback) {
    var mapped = [];
    foreach(collection, function (value, key, coll) {
        mapped.push(callback(value, key, coll));
    });
    return mapped;
};

var mapToObject = function (collection, callback, keyCallback) {
    var mapped = {};
    foreach(collection, function (value, key, coll) {
        key = keyCallback ? keyCallback(key, value) : key;
        mapped[key] = callback(value, key, coll);
    });
    return mapped;
};

var appendKey = function (appendingString, collection) {
    return map(collection, identity, function (key) {
        return appendingString + key;
    });
};

var map = function (collection, callback, keyCallback) {
    return isArray(collection) ?
        mapToArray(collection, callback) :
        mapToObject(collection, callback, keyCallback);
};

var pluck = function(collection, key) {
    return map(collection, function (value) {
        return value[key];
    });
};

var call = function (collection, functionName, args) {
    return map(collection, function (object, name) {
        return object[functionName].apply(object, args || []);
    });
};

var keys = function (collection) {
    return mapToArray(collection, function (val, key) {
        return key;
    });
};

var values = function (collection) {
    return mapToArray(collection, function (val) {
        return val;
    });
};

var reduce = function (collection, callback, initialAccumulation) {
    var accumulation = initialAccumulation;
    foreach(collection, function (val, key) {
        accumulation = callback(accumulation, val, key, collection);
    });
    return accumulation;
};

var filter = function (collection, callback) {
    var filtered;

    if(isArray(collection)) {
        filtered = [];
        foreach(collection, function (val, key, coll) {
            if(callback(val, key, coll)) {
                filtered.push(val);
            }
        });
    }
    else {
        filtered = {};
        foreach(collection, function (val, key, coll) {
            if(callback(val, key, coll)) {
                filtered[key] = val;
            }
        });
    }

    return filtered;
};

var union = function () {
    var united = {}, i;
    for(i = 0; i < arguments.length; i += 1) {
        foreach(arguments[i], function (value, key) {
            united[key] = value;
        });
    }
    return united;
};

var subSet = function (object, subsetKeys) {
    return filter(object, function (value, key) {
        return indexOf(subsetKeys, key) !== -1;
    });
};

var excludedSet = function (object, excludedKeys) {
    return filter(object, function (value, key) {
        return indexOf(excludedKeys, key) === -1;
    });
};

var remove = function (collection, item) {
    return filter(collection, function (element) {
        return element !== item;
    });
};

// call the variable if it is a function.
var callIfFunction = function (fn) {
    var args = Array.prototype.slice.call(arguments, 1);
    if(isFunction(fn)) {
        return fn.apply(null, args);
    }
};


//execute callback immediately and at most one time on the minimumInterval,
//ignore block attempts
var throttle = function (minimumInterval, callback) {
    var timeout = null;
    return function () {
        var that = this, args = arguments;
        if(timeout === null) {
            timeout = setTimeout(function () {
                timeout = null;
            }, minimumInterval);
            callback.apply(that, args);
        }
    };
};

//execute callback at most one time on the minimumInterval
var debounce = function (minimumInterval, callback, isImmediate) {
    var timeout = null;
    var isAttemptBlockedOnInterval = false;
    return function () {
        var that = this, args = arguments;
        if(timeout === null) {
            timeout = setTimeout(function () {
                if(!isImmediate || isAttemptBlockedOnInterval) {
                    callback.apply(that, args);
                }
                isAttemptBlockedOnInterval = false;
                timeout = null;
            }, minimumInterval);
            if(isImmediate) {
                callback.apply(that, args);
            }
        }
        else {
            isAttemptBlockedOnInterval = true;
        }
    };
};

var generateUniqueID = (function () {
    var count = 0;
    return function () {
        return count += 1;
    };
}());

var mixinPubSub = function (object) {
    object = object || {};
    var topics = {};

    object.publish = function (topic, data) {
        foreach(topics[topic], function (callback) {
            callback(data);
        });
    };

    object.subscribe = function (topic, callback) {
        topics[topic] = topics[topic] || [];
        topics[topic].push(callback);
    };

    object.unsubscribe = function (callback) {
        foreach(topics, function (subscribers) {
            var index = indexOf(subscribers, callback);
            if(index !== -1) {
                subscribers.splice(index, 1);
            }
        });
    };

    return object;
};


// queryjs
// https://github.com/DubFriend/queryjs
// MIT License 2014 Brian Detering
var queryjs = (function () {
    'use strict';

    var queryjs = {};

    var parse = function (url) {
        var domain = '', hash = '';
        var getParameterStrings = function () {
            var isHash = url.indexOf('#') !== -1,
                isQuery = url.indexOf('?') !== -1,
                queryString = '';

            if(isQuery) {
                queryString = url.split('?')[1] || '';
                if(isHash) {
                    queryString = queryString.split('#')[0] || '';
                }
            }

            if(isQuery) {
                domain = url.split('?')[0] || '';
            }
            else if (isHash) {
                domain = url.split('#')[0] || '';
            }
            else {
                domain = url;
            }

            if(isHash) {
                hash = url.split('#')[1] || '';
            }

            return queryString ? queryString.split('&') : [];
        };

        var parameterStrings = getParameterStrings(url),
            params = {},
            key, value, i;

        for(i = 0; i < parameterStrings.length; i += 1) {
            key = parameterStrings[i].split('=')[0];
            value = parameterStrings[i].split('=')[1];
            params[key] = value;
        }

        return {
            url: domain || '',
            hash: hash || '',
            parameters: params
        };
    };

    var stringify = function (parsed) {
        var key, parameterStrings = [];

        foreach(parsed.parameters, function (value, key) {
            parameterStrings.push(key + '=' + parsed.parameters[key]);
        });

        return parsed.url +
            (parameterStrings.length > 0 ?
                '?' + parameterStrings.join('&') : '') +
            (parsed.hash ? '#' + parsed.hash : '');
    };

    queryjs.get = function (url) {
        return parse(url).parameters;
    };

    queryjs.set = function (url, params) {
        var parsed = parse(url);
        parsed.parameters = union(parsed.parameters, params);
        return stringify(parsed);
    };

    return queryjs;

}());

var $getAnyForminatorModule = function (preSelector, name, moduleName) {
    return $(
        preSelector +
        (moduleName ? '-' + moduleName : '') +
        (name ? '-' + name : '')
    );
};

var $getForminatorByClass = partial($getAnyForminatorModule, '.frm');
var createBaseInput = function (fig, my) {
    var self = mixinPubSub(),
        $self = fig.$;

    self.getType = function () {
        throw 'implement me (return type. "text", "radio", etc.)';
    };

    self.$ = function (selector) {
        return selector ? $self.find(selector) : $self;
    };

    self.disable = function () {
        self.$().prop('disabled', true);
        self.publish('isEnabled', false);
    };

    self.enable = function () {
        self.$().prop('disabled', false);
        self.publish('isEnabled', true);
    };

    my.equalTo = function (a, b) {
        return a === b;
    };

    my.publishChange = (function () {
        var oldValue;
        return function (e, domElement) {
            var newValue = self.get();
            if(!my.equalTo(newValue, oldValue)) {
                self.publish('change', { e: e, domElement: domElement });
            }
            oldValue = newValue;
        };
    }());

    return self;
};


var createInput = function (fig, my) {
    var self = createBaseInput(fig, my);

    self.get = function () {
        return self.$().val();
    };

    self.set = function (newValue) {
        self.$().val(newValue);
    };

    self.clear = function () {
        self.set('');
    };

    my.buildSetter = function (callback) {
        return function (newValue) {
            callback.call(self, newValue);
        };
    };

    return self;
};

var inputEqualToArray = function (a, b) {
    a = isArray(a) ? a : [a];
    b = isArray(b) ? b : [b];

    var isEqual = true;
    if(a.length !== b.length) {
        isEqual = false;
    }
    else {
        foreach(a, function (value) {
            if(!inArray(b, value)) {
                isEqual = false;
            }
        });
    }

    return isEqual;
};

var createInputButton = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'button';
    };

    self.$().on('change', function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputCheckbox = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'checkbox';
    };

    self.get = function () {
        var values = [];
        self.$().filter(':checked').each(function () {
            values.push($(this).val());
        });
        return values;
    };

    self.set = function (newValues) {
        newValues = isArray(newValues) ? newValues : [newValues];

        self.$().each(function () {
            $(this).prop('checked', false);
        });

        foreach(newValues, function (value) {
            self.$().filter('[value="' + value + '"]')
                .prop('checked', true);
        });
    };

    my.equalTo = inputEqualToArray;

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputEmail = function (fig) {
    var my = {},
        self = createInputText(fig, my);

    self.getType = function () {
        return 'email';
    };

    return self;
};

var createInputFile = function (fig) {
    var my = {},
        self = createBaseInput(fig, my);

    self.getType = function () {
        return 'file';
    };

    self.get = function () {
        return last(self.$().val().split('\\'));
    };

    self.clear = function () {
        // http://stackoverflow.com/questions/1043957/clearing-input-type-file-using-jquery
        this.$().each(function () {
            $(this).wrap('<form>').closest('form').get(0).reset();
            $(this).unwrap();
        });
    };

    self.$().change(function (e) {
        my.publishChange(e, this);
        // self.publish('change', self);
    });

    return self;
};

var createInputHidden = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'hidden';
    };

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};
var createInputMultipleFile = function (fig) {
    var my = {},
        self = createBaseInput(fig, my);

    self.getType = function () {
        return 'file[multiple]';
    };

    self.get = function () {
        // http://stackoverflow.com/questions/14035530/how-to-get-value-of-html-5-multiple-file-upload-variable-using-jquery
        var fileListObject = self.$().get(0).files || [],
            names = [], i;

        for(i = 0; i < (fileListObject.length || 0); i += 1) {
            names.push(fileListObject[i].name);
        }

        return names;
    };

    self.clear = function () {
        // http://stackoverflow.com/questions/1043957/clearing-input-type-file-using-jquery
        this.$().each(function () {
            $(this).wrap('<form>').closest('form').get(0).reset();
            $(this).unwrap();
        });
    };

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputMultipleSelect = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'select[multiple]';
    };

    self.get = function () {
        return self.$().val() || [];
    };

    self.set = function (newValues) {
        self.$().val(
            newValues === '' ? [] : isArray(newValues) ? newValues : [newValues]
        );
    };

    my.equalTo = inputEqualToArray;

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputPassword = function (fig) {
    var my = {},
        self = createInputText(fig, my);

    self.getType = function () {
        return 'password';
    };

    return self;
};

var createInputRadio = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'radio';
    };

    self.get = function () {
        return self.$().filter(':checked').val() || null;
    };

    self.set = function (newValue) {
        if(!newValue) {
            self.$().each(function () {
                $(this).prop('checked', false);
            });
            // self.$().prop('checked', false);
        }
        else {
            self.$().filter('[value="' + newValue + '"]').prop('checked', true);
        }
    };

    // self.set = my.buildSetter(function (newValue) {
    //     console.log('set : ', newValue, self.$());
    //     if(!newValue) {
    //         self.$().prop('checked', false);
    //     }
    //     else {
    //         self.$().filter('[value="' + newValue + '"]').prop('checked', true);
    //     }
    // });

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputRange = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'range';
    };

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputSelect = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'select';
    };

    self.$().change(function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputText = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'text';
    };


    self.$().on('change keyup keydown', function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputTextarea = function (fig) {
    var my = {},
        self = createInput(fig, my);

    self.getType = function () {
        return 'textarea';
    };

    self.$().on('change keyup keydown', function (e) {
        my.publishChange(e, this);
    });

    return self;
};

var createInputURL = function (fig) {
    var my = {},
        self = createInputText(fig, my);

    self.getType = function () {
        return 'url';
    };

    return self;
};

var buildFormInputs = function (fig) {
    var inputs = {},
        $self = fig.$;

    var constructor = fig.constructorOverride || {
        button: createInputButton,
        text: createInputText,
        url: createInputURL,
        email: createInputEmail,
        password: createInputPassword,
        range: createInputRange,
        textarea: createInputTextarea,
        select: createInputSelect,
        'select[multiple]': createInputMultipleSelect,
        radio: createInputRadio,
        checkbox: createInputCheckbox,
        file: createInputFile,
        'file[multiple]': createInputMultipleFile,
        hidden: createInputHidden
    };

    var addInputsBasic = function (type, selector) {
        var $input = isObject(selector) ? selector : $self.find(selector);

        $input.each(function () {
            var name = $(this).attr('name');
            inputs[name] = constructor[type]({
                $: $(this)
            });
        });
    };

    var addInputsGroup = function (type, selector) {
        var names = [],
            $input = isObject(selector) ? selector : $self.find(selector);

        if(isObject(selector)) {
            inputs[$input.attr('name')] = constructor[type]({
                $: $input
            });
        }
        else {
            // group by name attribute
            $input.each(function () {
                var name = $(this).attr('name');

                if(indexOf(names, $(this).attr('name')) === -1) {
                    names.push($(this).attr('name'));
                }
            });

            foreach(names, function (name) {
                inputs[name] = constructor[type]({
                    $: $self.find('input[name="' + name + '"]')
                });
            });
        }
    };


    if($self.is('input, select, textarea')) {
        if($self.is('input[type="button"], button, input[type="submit"]')) {
            addInputsBasic('button', $self);
        }
        else if($self.is('textarea')) {
            addInputsBasic('textarea', $self);
        }
        else if(
            $self.is('input[type="text"]') ||
            $self.is('input') && !$self.attr('type')
        ) {
            addInputsBasic('text', $self);
        }
        else if($self.is('input[type="password"]')) {
            addInputsBasic('password', $self);
        }
        else if($self.is('input[type="email"]')) {
            addInputsBasic('email', $self);
        }
        else if($self.is('input[type="url"]')) {
            addInputsBasic('url', $self);
        }
        else if($self.is('input[type="range"]')) {
            addInputsBasic('range', $self);
        }
        else if($self.is('select')) {
            if($self.is('[multiple]')) {
                addInputsBasic('select[multiple]', $self);
            }
            else {
                addInputsBasic('select', $self);
            }
        }
        else if($self.is('input[type="file"]')) {
            if($self.is('[multiple]')) {
                addInputsBasic('file[multiple]', $self);
            }
            else {
                addInputsBasic('file', $self);
            }
        }
        else if($self.is('input[type="hidden"]')) {
            addInputsBasic('hidden', $self);
        }
        else if($self.is('input[type="radio"]')) {
            addInputsGroup('radio', $self);
        }
        else if($self.is('input[type="checkbox"]')) {
            addInputsGroup('checkbox', $self);
        }
        else {
            // in all other cases default to a "text" input interface.
            addInputsBasic('text', $self);
        }
    }
    else {
        addInputsBasic('button', 'input[type="button"], button, input[type="submit"]');
        addInputsBasic('text', 'input[type="text"]');
        addInputsBasic('password', 'input[type="password"]');
        addInputsBasic('email', 'input[type="email"]');
        addInputsBasic('url', 'input[type="url"]');
        addInputsBasic('range', 'input[type="range"]');
        addInputsBasic('textarea', 'textarea');
        addInputsBasic('select', 'select:not([multiple])');
        addInputsBasic('select[multiple]', 'select[multiple]');
        addInputsBasic('file', 'input[type="file"]:not([multiple])');
        addInputsBasic('file[multiple]', 'input[type="file"][multiple]');
        addInputsBasic('hidden', 'input[type="hidden"]');
        addInputsGroup('radio', 'input[type="radio"]');
        addInputsGroup('checkbox', 'input[type="checkbox"]');
    }

    return inputs;
};

var createFactory = function (fig) {
    var self = {};

    var buildModuleIfExists = function (fn, $module) {
        return function () {
            var args = argumentsToArray(arguments);
            if($module.length) {
                return fn.apply(null, [$module].concat(args));
            }
        };
    };

    self.input = {
        text: createInputText,
        textarea: createInputTextarea,
        select: createInputSelect,
        radio: createInputRadio,
        checkbox: createInputCheckbox,
        file: createInputFile,
        button: createInputButton,
        hidden: createInputHidden,
        range: createInputRange
    };

    var getMappedFormInputs = function ($form) {
        return map(
            buildFormInputs({ $: $form, factory: self }),
            function (input) {
                return createFormGroup({ input: input });
            }
        );
    };

    self.form = buildModuleIfExists(function ($module) {
        return createForm({
            $: $module,
            ajax: ajax,
            validate: fig.validate,
            url: url,
            inputs: getMappedFormInputs($module)
        });
    }, $getModuleByClass(''));

    self.list = buildModuleIfExists(function ($module, request) {
        return createList({
            $: $module,
            fieldMap: fieldMap,
            request: request,
            uniquelyIdentifyingFields: uniquelyIdentifyingFields,
            deleteConfirmation: deleteConfirmation
        });
    }, $getModuleByClass('list'));

    self.newItemButton = buildModuleIfExists(function ($module) {
        return createNewItemButton({ $: $module });
    }, $getModuleByClass('new'));

    self.request = function () {
        return createRequest({
            ajax: function (fig) {
                $.ajax(fig);
            },
            url: url
        });
    };

    self.search = buildModuleIfExists(function ($module, request) {
        return createSearch({
            $: $module,
            isInstantSearch: fig.isInstantSearch === false ? false : true,
            request: request,
            inputs: getMappedFormInputs($module)
        });
    }, $getModuleByClass('search'));

    self.ordinator = buildModuleIfExists(function ($module, request) {
        return createOrdinator({
            $: $module,
            request: request,
            orderIcons: fig.orderIcons
        });
    }, $getModuleByClass('ordinator'));

    self.paginator = function (request) {
        return createPaginator({
            name: name,
            request: request,
            gotoPage: self.gotoPage()
        });
    };

    self.gotoPage = buildModuleIfExists(function ($module) {
        return createGotoPage({
            $: $module,
            inputs: getMappedFormInputs($module)
        });
    }, $getModuleByClass('goto-page'));

    return self;
};

$.fn.inputVal = function (newValue) {
    var $self = $(this);

    var inputs = buildFormInputs({ $: $self });

    if($self.is('input, textarea, select')) {
        if(typeof newValue === 'undefined') {
            return inputs[$self.attr('name')].get();
        }
        else {
            inputs[$self.attr('name')].set(newValue);
            return $self;
        }
    }
    else {
        if(typeof newValue === 'undefined') {
            return call(inputs, 'get');
        }
        else {
            foreach(newValue, function (value, inputName) {
                if(inputs[inputName]){
                    inputs[inputName].set(value);
                }
            });
            return $self;
        }
    }
};

$.fn.inputOnChange = function (callback) {
    var $self = $(this);
    var inputs = buildFormInputs({ $: $self });
    foreach(inputs, function (input) {
        input.subscribe('change', function (data) {
            callback.call(data.domElement, data.e);
        });
    });
    return $self;
};

$.fn.inputDisable = function () {
    var $self = $(this);
    call(buildFormInputs({ $: $self }), 'disable');
    return $self;
};

$.fn.inputEnable = function () {
    var $self = $(this);
    call(buildFormInputs({ $: $self }), 'enable');
    return $self;
};

$.fn.inputClear = function () {
    var $self = $(this);
    call(buildFormInputs({ $: $self }), 'clear');
    return $self;
};

}(jQuery));

(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? factory(require('jquery')) :
  typeof define === 'function' && define.amd ? define(['jquery'], factory) :
  (factory(global.jQuery));
}(this, (function ($) { 'use strict';

  $ = $ && $.hasOwnProperty('default') ? $['default'] : $;

  function identity(x) {
    return x;
  }
  function isArray(value) {
    return $.isArray(value);
  }
  function foreach(collection, callback) {
    if (collection) {
      Object.keys(collection).forEach(function (i) {
        return callback(collection[i], i, collection);
      });
    }
  }
  function last(array) {
    return array[array.length - 1];
  }
  function extend() {
    var extended = {};

    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    foreach(args, function (o) {
      foreach(o, function (val, key) {
        extended[key] = val;
      });
    });
    return extended;
  }
  function mapToArray(collection, callback) {
    var mapped = [];
    foreach(collection, function (value, key, coll) {
      mapped.push(callback(value, key, coll));
    });
    return mapped;
  }
  function mapToObject(collection, callback, keyCallback) {
    var mapped = {};
    foreach(collection, function (value, key, coll) {
      // eslint-disable-next-line no-param-reassign
      key = keyCallback ? keyCallback(key, value) : key;
      mapped[key] = callback(value, key, coll);
    });
    return mapped;
  }
  function map(collection, callback, keyCallback) {
    return isArray(collection) ? mapToArray(collection, callback) : mapToObject(collection, callback, keyCallback);
  }
  function pluck(arrayOfObjects, key) {
    return map(arrayOfObjects, function (val) {
      return val[key];
    });
  }
  function filter(collection, callback) {
    var filtered;

    if (isArray(collection)) {
      filtered = [];
      foreach(collection, function (val, key, coll) {
        if (callback(val, key, coll)) {
          filtered.push(val);
        }
      });
    } else {
      filtered = {};
      foreach(collection, function (val, key, coll) {
        if (callback(val, key, coll)) {
          filtered[key] = val;
        }
      });
    }

    return filtered;
  }

  $.fn.repeaterVal = function () {
    var parse = function parse(raw) {
      var parsed = [];
      foreach(raw, function (val, key) {
        var parsedKey = [];

        if (key !== 'undefined') {
          parsedKey.push(key.match(/^[^[]*/)[0]);
          parsedKey = parsedKey.concat(map(key.match(/\[[^\]]*\]/g), function (bracketed) {
            return bracketed.replace(/[[\]]/g, '');
          }));
          parsed.push({
            val: val,
            key: parsedKey
          });
        }
      });
      return parsed;
    };

    function build(parsed) {
      if (parsed.length === 1 && (parsed[0].key.length === 0 || parsed[0].key.length === 1) && !parsed[0].key[0]) {
        return parsed[0].val;
      }

      foreach(parsed, function (p) {
        // eslint-disable-next-line no-param-reassign
        p.head = p.key.shift();
      });

      var grouped = function () {
        var mGrouped = {};
        foreach(parsed, function (p) {
          if (!mGrouped[p.head]) {
            mGrouped[p.head] = [];
          }

          mGrouped[p.head].push(p);
        });
        return mGrouped;
      }();

      var built;

      if (/^[0-9]+$/.test(parsed[0].head)) {
        built = [];
        foreach(grouped, function (group) {
          built.push(build(group));
        });
      } else {
        built = {};
        foreach(grouped, function (group, key) {
          built[key] = build(group);
        });
      }

      return built;
    }

    return build(parse($(this).inputVal()));
  };

  $.fn.repeater = function (fig) {
    // eslint-disable-next-line no-param-reassign
    fig = fig || {};
    var setList;
    $(this).each(function () {
      var $self = $(this);

      var show = fig.show || function () {
        $(this).show();
      };

      var hide = fig.hide || function (removeElement) {
        removeElement();
      };

      var $list = $self.find('[data-repeater-list]').first();

      var $filterNested = function $filterNested($items, repeaters) {
        return $items.filter(function () {
          return repeaters ? $(this).closest(pluck(repeaters, 'selector').join(',')).length === 0 : true;
        });
      };

      var $items = function $items() {
        return $filterNested($list.find('[data-repeater-item]'), fig.repeaters);
      };

      var $itemTemplate = $list.find('[data-repeater-item]').first().clone().hide();
      var $firstDeleteButton = $filterNested($filterNested($(this).find('[data-repeater-item]'), fig.repeaters).first().find('[data-repeater-delete]'), fig.repeaters);

      if (fig.isFirstItemUndeletable && $firstDeleteButton) {
        $firstDeleteButton.remove();
      }

      var getGroupName = function getGroupName() {
        var groupName = $list.data('repeater-list');
        return fig.$parent ? "".concat(fig.$parent.data('item-name'), "[").concat(groupName, "]") : groupName;
      };

      var initNested = function initNested($listItems) {
        if (fig.repeaters) {
          $listItems.each(function () {
            var $item = $(this);
            foreach(fig.repeaters, function (nestedFig) {
              $item.find(nestedFig.selector).repeater(extend(nestedFig, {
                $parent: $item
              }));
            });
          });
        }
      };

      var $foreachRepeaterInItem = function $foreachRepeaterInItem(repeaters, $item, cb) {
        if (repeaters) {
          foreach(repeaters, function (nestedFig) {
            cb.call($item.find(nestedFig.selector)[0], nestedFig);
          });
        }
      };

      function setIndexes($its, groupName, repeaters) {
        $its.each(function (index) {
          var $item = $(this);
          $item.data('item-name', "".concat(groupName, "[").concat(index, "]"));
          $filterNested($item.find('[name]'), repeaters).each(function () {
            var $input = $(this); // match non empty brackets (ex: "[foo]")

            var matches = $input.attr('name').match(/\[[^\]]+\]/g);
            var name = matches // strip "[" and "]" characters
            ? last(matches).replace(/\[|\]/g, '') : $input.attr('name');
            var newName = "".concat(groupName, "[").concat(index, "][").concat(name, "]").concat($input.is(':checkbox') || $input.attr('multiple') ? '[]' : '');
            $input.attr('name', newName);
            $foreachRepeaterInItem(repeaters, $item, function (nestedFig) {
              var $repeater = $(this);
              setIndexes($filterNested($repeater.find('[data-repeater-item]'), nestedFig.repeaters || []), "".concat(groupName, "[").concat(index, "]") + "[".concat($repeater.find('[data-repeater-list]').first().data('repeater-list'), "]"), nestedFig.repeaters);
            });
          });
        });
        $list.find('input[name][checked]').removeAttr('checked').prop('checked', true);
      }

      setIndexes($items(), getGroupName(), fig.repeaters);
      initNested($items());

      if (fig.initEmpty) {
        $items().remove();
      }

      if (fig.ready) {
        fig.ready(function () {
          setIndexes($items(), getGroupName(), fig.repeaters);
        });
      }

      var appendItem = function () {
        function setItemsValues($item, data, repeaters) {
          if (data || fig.defaultValues) {
            var inputNames = {};
            $filterNested($item.find('[name]'), repeaters).each(function () {
              var key = $(this).attr('name').match(/\[([^\]]*)(\]|\]\[\])$/)[1];
              inputNames[key] = $(this).attr('name');
            });
            $item.inputVal(map(filter(data || fig.defaultValues, function (val, name) {
              return inputNames[name];
            }), identity, function (name) {
              return inputNames[name];
            }));
          }

          $foreachRepeaterInItem(repeaters, $item, function (nestedFig) {
            var $repeater = $(this);
            $filterNested($repeater.find('[data-repeater-item]'), nestedFig.repeaters).each(function () {
              var fieldName = $repeater.find('[data-repeater-list]').data('repeater-list');

              if (data && data[fieldName]) {
                var $template = $(this).clone();
                $repeater.find('[data-repeater-item]').remove();
                foreach(data[fieldName], function (d) {
                  var $itm = $template.clone();
                  setItemsValues($itm, d, nestedFig.repeaters || []);
                  $repeater.find('[data-repeater-list]').append($itm);
                });
              } else {
                setItemsValues($(this), nestedFig.defaultValues, nestedFig.repeaters || []);
              }
            });
          });
        }

        return function ($item, data) {
          $list.append($item);
          setIndexes($items(), getGroupName(), fig.repeaters);
          $item.find('[name]').each(function () {
            $(this).inputClear();
          });
          setItemsValues($item, data || fig.defaultValues, fig.repeaters);
        };
      }();

      var addItem = function addItem(data) {
        var $item = $itemTemplate.clone();
        appendItem($item, data);

        if (fig.repeaters) {
          initNested($item);
        }

        show.call($item.get(0));
      };

      setList = function setList(rows) {
        $items().remove();
        foreach(rows, addItem);
      };

      $filterNested($self.find('[data-repeater-create]'), fig.repeaters).click(function () {
        addItem();
      });
      $list.on('click', '[data-repeater-delete]', function () {
        var self = $(this).closest('[data-repeater-item]').get(0);
        hide.call(self, function () {
          $(self).remove();
          setIndexes($items(), getGroupName(), fig.repeaters);
        });
      });
    });
    this.setList = setList;
    return this;
  };

})));
//# sourceMappingURL=jquery.repeater.js.map
