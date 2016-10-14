'use strict';

var accounts = require('./accounts.js'),
    blocks = require('./blocks.js'),
    candles = require('./candles.js'),
    common = require('./common.js'),
    delegates = require('./delegates.js'),
    orders = require('./orders.js'),
    statistics = require('./statistics.js'),
    transactions = require('./transactions.js');

module.exports = function (app) {
    accounts(app);
    blocks(app);
    candles(app);
    common(app);
    delegates(app);
    orders(app);
    statistics(app);
    transactions(app);
};
