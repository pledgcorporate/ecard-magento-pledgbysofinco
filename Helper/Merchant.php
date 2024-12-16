<?php

namespace Pledg\PledgPaymentGateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Merchant extends AbstractHelper
{
    /**
     * @throws \Exception
     */
    public function getMerchandUidByCountryCode(
        string $countryCode,
        string $merchantUid
    ) {
        $ret = null;

        if ($merchantUidDecoded = json_decode($merchantUid, true)) {
            /*
             * When these mapping values are stored in database,
             * magento encapsulates them in an array indexed by a timestamp.
             * Here's an example:
             * 
             * {
             *   "_1732606628330_330": {
             *       "country": "FR",
             *       "api_key": "mer_8273ddc1-b78a-4282-ad92-0928c96b27ff"
             *   },
             *   "_1732617017746_746": {
             *       "country": "BE",
             *       "api_key": "mer_784ea5e7-4c81-498e-b046-371195e76879"
             *   }
             * }
             * 
             */

            foreach ($merchantUidDecoded as $key => $value) {
                if ($countryCode === $value['country']
                    && !empty($value['api_key'])
                ) {
                    $ret = $value['api_key'];
                }
            }
        } else {
            throw new \Exception(sprintf('Impossible to decode merchantUid %s', $merchantUid));
        }

        if (empty($ret)) {
            throw new \Exception(sprintf('No merchant uid were found for country code %s', $countryCode));
        }

        return $ret;
    }
}
