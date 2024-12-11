define(['jquery', 'pledgLib'], function($, pledgLib){

    "use strict";

    return {

        previousPos: 0,
        previousWidth: 0,
        newPos: 0,
        newWidth: 0,
        movingWidth: 0,
        movingPos: 0,
        expandedWidth: 0,

        expanded: false,
        expR: 0,
        expL: 0,

        // Expand width to the right then expand the position + move back the width
        expandRight: function (self) {
            const pledgGet = document.getElementById.bind(document);
            const pledgQuery = document.querySelector.bind(document);

            let line = pledgGet('pledg-primary-line');
            let popupLine = pledgGet('pledg-popup-line');

            if (self.movingWidth < self.expandedWidth && !(self.expanded)) {
                self.movingWidth++;
                line.style.width = self.movingWidth + 'px';
                popupLine.style.width = self.movingWidth + 'px';
            } else {
                if (!(self.expanded)) self.expanded = true;

                if (self.movingPos < self.newPos) {
                    self.movingWidth--;
                    self.movingPos++;

                    line.style.width = self.movingWidth + 'px';
                    line.style.left = self.movingPos + 'px';

                    popupLine.style.width = self.movingWidth + 'px';
                    popupLine.style.left = self.movingPos + 'px';
                } else {
                    clearInterval(self.expR);

                    self.previousPos = self.newPos;
                    self.previousWidth = self.newWidth;

                    self.expanded = false;
                    let nav = pledgQuery('.pledg-widget-nav');
                    nav.classList.remove('animate');
                }
            }
        },

        // Expand width to the left then expand the position + move back the width
        expandLeft: function (self) {
            const pledgGet = document.getElementById.bind(document);
            const pledgQuery = document.querySelector.bind(document);

            let line = pledgGet('pledg-primary-line');
            let popupLine = pledgGet('pledg-popup-line');

            if (self.movingPos > self.newPos && !(self.expanded)) {
                self.movingWidth++;
                self.movingPos--;

                line.style.width = self.movingWidth + 'px';
                line.style.left = self.movingPos + 'px';

                popupLine.style.width = self.movingWidth + 'px';
                popupLine.style.left = self.movingPos + 'px';
            } else {
                if (!(self.expanded)) self.expanded = true;

                if (self.movingWidth > self.newWidth) {
                    self.movingWidth--;
                    line.style.width = self.movingWidth + 'px';
                    popupLine.style.width = self.movingWidth + 'px';
                } else {
                    clearInterval(self.expL);

                    self.previousPos = self.newPos;
                    self.previousWidth = self.newWidth;

                    self.expanded = false;
                    let nav = pledgQuery('.pledg-widget-nav');
                    nav.classList.remove('animate');
                }
            }
        },

        // Switch merchant icon, update caption and popup payment schedule
        switchMerchant: function (element) {
            const pledgGet = document.getElementById.bind(document);
            const pledgQuery = document.querySelector.bind(document);
            const pledgQueryAll = document.querySelectorAll.bind(document);

            let nav = pledgQuery('.pledg-widget-nav');
            let captionElem = pledgQuery('#pledg-widget-caption span');

            if (
                element.classList.contains('active')
                || nav.classList.contains('animate')
            ) {
                return;
            }

            nav.classList.add('animate');

            let caption = $(element)[0].dataset['caption'];
            this.fadeTextIn(captionElem, caption);

            let merchants = pledgQueryAll('.merchantIcon');
            [].forEach.call(merchants, elem => {
                elem.classList.remove('active');
            });

            this.newPos = element.offsetLeft;
            this.newWidth = element.offsetWidth;
            this.movingWidth = this.previousWidth; // Initiate width that will animate
            this.movingPos = this.previousPos; // Initiate position that will animate

            if(this.newPos >= this.previousPos) {
                this.expandedWidth = ((this.newPos - this.previousPos) + this.newWidth); // Maximum width
                this.expR = setInterval(this.expandRight, 0.1, this);
            } else {
                this.expandedWidth = ((this.previousPos - this.newPos) + this.newWidth); // Maximum width
                this.expL = setInterval(this.expandLeft, 0.1, this);
            }

            // Update active div (widget & poup)
            let elementsToActive = pledgQueryAll('.merchantIcon' + $(element)[0].dataset['key']);
            [].forEach.call(elementsToActive, elem => {
                elem.classList.add('active');
            });

            let active = pledgQuery('#pledg-primary-widget .active');

            // Update payment schedule
            pledgGet('pledg-popup-payment-schedule').innerHTML = pledgLib.formatPaymentSchedule(
                JSON.parse($(active)[0].dataset.schedule),
                this.getDataset('options')
            );

            // Update payment steps
            let sCaption = '';
            let sSecondBulletPoint = '';
            let sFourthBulletPoint = '';

            let scheduleType = $(element)[0].dataset['type'];
            if ('installment' === scheduleType) {
                sCaption = this.getCaption('installmentCaption');
                sSecondBulletPoint = this.getCaption('installmentSecondBulletPoint');
                sFourthBulletPoint = this.getCaption('installmentFourthBulletPoint');
            } else if ('deferred' === scheduleType) {
                sCaption = this.getCaption('deferredCaption');
                sSecondBulletPoint = this.getCaption('deferredSecondBulletPoint');
                sFourthBulletPoint = this.getCaption('deferredFourthBulletPoint');
            }

            pledgGet('pledg-bnpl-caption').textContent = sCaption;
            pledgQueryAll('#pledg-popup-howto>.pledg-popup-step>p')[1].textContent = sSecondBulletPoint;
            pledgQueryAll('#pledg-popup-howto>.pledg-popup-step>p')[3].textContent = sFourthBulletPoint;

            // Update fees caption
            pledgGet('pledg-fees-caption').textContent = '';
            if ($(element)[0].dataset.fees == 0) {
                pledgGet('pledg-fees-caption').textContent = this.getCaption('feesCaption');
            }
        },

        fadeTextIn: function (elem, content) {
            elem.style.opacity = '0';
            setTimeout(function() {
                elem.innerHTML = content;
            }, 200);
            setTimeout(function() {
                elem.style.opacity = '1';
            }, 200);
        },

        /*********************
         ** popup animation **
         ********************/
        showPopup: function (element) {
            const pledgGet = document.getElementById.bind(document);
            let pledgOverlay = pledgGet('pledg-popup-overlay');
            pledgOverlay.classList.add('show');
        },

        hidePopup: function () {
            const pledgGet = document.getElementById.bind(document);
            let pledgOverlay = pledgGet('pledg-popup-overlay');
            pledgOverlay.classList.remove('show');
        },

        // initiateOverlay = function () {
        initiateOverlay: function () {
            const pledgGet = document.getElementById.bind(document);

            let pledgOverlay = pledgGet('pledg-popup-overlay');

            pledgOverlay.innerHTML = pledgGet('pledg-overlay-content').innerHTML;
            pledgGet('pledg-overlay-content').innerHTML = '';

            // Close popup on outside click
            pledgOverlay.addEventListener('click', function() {
                hidePopup();
            });

            let pledgPopup = pledgGet('pledg-popup-widget');

            pledgPopup.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            });
        },

        getDataset: function(dsKey) {
            let dataset = $('#pledg-widget').data(dsKey);
            return dataset;
        },

        getCaption: function(key) {
            let ret = '';
            let arrCaptions = this.getDataset('captions');

            if (arrCaptions[key] !== undefined) {
                ret = arrCaptions[key];
            }

            return ret;
        },

        getOption: function(key) {
            let ret = '';
            let arrOptions = this.getDataset('options');

            if (arrOptions[key] !== undefined) {
                ret = arrOptions[key];
            }

            return ret;
        },

        initiateWidget: function() {
            const pledgGet = document.getElementById.bind(document);
            const pledgQuery = document.querySelector.bind(document);
            const pledgQueryAll = document.querySelectorAll.bind(document);

            let line = pledgGet('pledg-primary-line');
            let popupLine = pledgGet('pledg-popup-line');
            let active = pledgQuery('#pledg-primary-widget .active');
            let captionElem = pledgQuery('#pledg-widget-caption span');

            this.previousPos = active.offsetLeft;
            this.previousWidth = active.offsetWidth;

            line.style.left = this.previousPos + 'px';
            line.style.width = this.previousWidth + 'px';

            popupLine.style.left = this.previousPos + 'px';
            popupLine.style.width = this.previousWidth + 'px';

            captionElem.innerHTML = active.dataset['caption'];

            // Update payment steps
            let sCaption = '';
            let sSecondBulletPoint = '';
            let sFourthBulletPoint = '';

            let scheduleType = active.dataset['type'];
            if ('installment' === scheduleType) {
                sCaption = this.getCaption('installmentCaption');
                sSecondBulletPoint = this.getCaption('installmentSecondBulletPoint');
                sFourthBulletPoint = this.getCaption('installmentFourthBulletPoint');
            } else if ('deferred' === scheduleType) {
                sCaption = this.getCaption('deferredCaption');
                sSecondBulletPoint = this.getCaption('deferredSecondBulletPoint');
                sFourthBulletPoint = this.getCaption('deferredFourthBulletPoint');
            }

            pledgGet('pledg-bnpl-caption').textContent = sCaption;
            pledgQueryAll('#pledg-popup-howto>.pledg-popup-step>p')[1].textContent = sSecondBulletPoint;
            pledgQueryAll('#pledg-popup-howto>.pledg-popup-step>p')[3].textContent = sFourthBulletPoint;

            // Update payment schedule
            pledgGet('pledg-popup-payment-schedule').innerHTML = pledgLib.formatPaymentSchedule(
                JSON.parse($(active)[0].dataset.schedule),
                this.getDataset('options')
            );
        },

        onCartUpdate: function (urlWidgetUpdateController, widgetType) {
            const pledgGet = document.getElementById.bind(document);
            const pledgQuery = document.querySelector.bind(document);

            let pledgWidget = pledgGet('pledg-widget');

            if (
                typeof pledgWidget === 'undefined'
                || pledgWidget === null
            ) {
                return;
            }

            // the widget update controller URL
            if (
                typeof urlWidgetUpdateController !== 'string'
                || urlWidgetUpdateController.trim().length <= 0
            ) {
                return;
            }

            let defaultActiveMerchantClass = null;
            let defaultActiveMerchant = pledgQuery('#pledg-primary-widget .merchantIcon.active');

            if (
                defaultActiveMerchant !== null
                && defaultActiveMerchant.className !== ''
            ) {
                let arrClasses = defaultActiveMerchant.className.split(' ');
                for (let i = 0; i < arrClasses.length; i++) {
                    let classPattern = /merchantIcon[0-9]+/;
                    if (classPattern.test(arrClasses[i])) {
                        defaultActiveMerchantClass = arrClasses[i];
                        break;
                    }
                }
            }

            const url = new URL(urlWidgetUpdateController);

            url.searchParams.set("widgetType", widgetType);

            if (defaultActiveMerchantClass !== null) {
                url.searchParams.set("activeMerchant", defaultActiveMerchantClass);
            }

            const xhttpCart = new XMLHttpRequest();
            xhttpCart.onreadystatechange = function () {

                if (this.readyState === 4 && this.status === 200) {
                    let jsonResponse = JSON.parse(this.responseText);
                    pledgWidget.innerHTML = jsonResponse.html;

                    initiateWidget();
                    switchMerchant(defaultActiveMerchant);
                }

            }

            xhttpCart.open('GET', url.toString());
            xhttpCart.send();
        },

    }

});
