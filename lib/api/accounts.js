'use strict';

var request = require('request'),
    async = require('async');

module.exports = function (app) {
    function Account () {
        this.validAddress = function (address) {
            return (
                typeof address === 'string' && address.match(/^[0-9]{1,21}[S|l]$/g)
            );
        };

        this.getAccount = function (address, cb) {
            request.get({
                url : app.get('lisk address') + '/api/accounts?address=' + address,
                json : true
            }, function (err, response, body) {
                if (err || response.statusCode !== 200) {
                    return cb(err || 'Response was unsuccessful');
                } else if (body.success === true) {
                    var account = body.account;
                    account.usd = exchange.LISKUSD(account.balance);
                    account.knowledge = knowledge.inAccount(account);
                    return cb(null, account);
                } else {
                    return cb(body.error);
                }
            });
        };

        this.getDelegate = function (account, cb) {
            if (!account.publicKey) {
                return cb(null, account);
            }
            request.get({
                url : app.get('lisk address') + '/api/delegates/get?publicKey=' + account.publicKey,
                json : true
            }, function (err, response, body) {
                if (err || response.statusCode !== 200) {
                    return cb(err || 'Response was unsuccessful');
                } else if (body.success === true) {
                    account.delegate = body.delegate;
                } else {
                    account.delegate = null;
                }
                return cb(null, account);
            });
        };

        this.getForged = function (account, cb) {
            if (!account.delegate) {
                return cb(null, account);
            }
            request.get({
                url : app.get('lisk address') + '/api/delegates/forging/getForgedByAccount?generatorPublicKey=' + account.publicKey,
                json : true
            }, function (err, response, body) {
                if (err || response.statusCode !== 200) {
                    return cb(err || 'Response was unsuccessful');
                } else if (body.success === true) {
                    account.delegate.forged = body.forged;
                } else {
                    account.delegate.forged = 0;
                }
                return cb(null, account);
            });
        };
    }

    this.getAccount = function (address, error, success) {
        var account = new Account();

        if (!account.validAddress(address)) {
            return error({ success : false, error : 'Missing/Invalid address parameter' });
        }
        async.waterfall([
            function (cb) {
                account.getAccount(address, cb);
            },
            function (result, cb) {
                account.getDelegate(result, cb);
            },
            function (result, cb) {
                account.getForged(result, cb);
            },
        ], function (err, result) {
            if (err) {
                return error({ success : false, error : err });
            } else {
                result.success = true;
                return success(result);
            }
        });
    };

    function TopAccount () {
        this.getKnowledge = function (account, cb) {
            account.knowledge = knowledge.inAccount(account);

            if (!account.knowledge && account.publicKey) {
                return getDelegate(account, cb);
            } else {
                return cb(null, account);
            }
        };

        // Private

        var getDelegate = function (account, cb) {
            request.get({
                url : app.get('lisk address') + '/api/delegates/get?publicKey=' + account.publicKey,
                json : true
            }, function (err, response, body) {
                if (err || response.statusCode !== 200) {
                    return cb(err || 'Response was unsuccessful');
                } else if (body.success === true) {
                    account.knowledge = knowledge.inDelegate(body.delegate);
                } else {
                    account.knowledge = null;
                }
                return cb(null, account);
            });
        };
    }

    this.getTopAccounts = function (query, error, success) {
        var topAccount = new TopAccount();

        request.get({
            url : app.get('lisk address') + '/api/accounts/top?&offset=' + param(query.offset, 0) + '&limit=' + param(query.limit, 100),
            json : true
        }, function (err, response, body) {
            if (err || response.statusCode !== 200) {
                return error({ success : false, error : (err || 'Response was unsuccessful') });
            } else if (body.success === true) {
                async.forEach(body.accounts, function (a, cb) {
                    a.usd = exchange.LISKUSD(a.balance);
                    topAccount.getKnowledge(a, cb);
                }, function () {
                    return success({ success : true, accounts : body.accounts });
                });
            } else {
                return error({ success : false, error : body.error });
            }
        });
    };

    // Private

    var exchange = app.exchange,
        knowledge = app.knownAddresses;

    var param = function (p, d) {
        p = parseInt(p);

        if (isNaN(p) || p < 0) {
            return d;
        } else {
            return p;
        }
    };
};
