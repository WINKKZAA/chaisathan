<?php
/**
 * @filesource Kotchasan/Promptpay.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Kotchasan;

/**
 * สร้าง paload สำหรับการชำระเงินด้วย QR Code (Prompt Pay)
 * ตามมาตรฐาน EMV®
 * อ้างอิงข้อมูลจาก https://www.bot.or.th/Thai/PaymentSystems/StandardPS/Documents/ThaiQRCode_Payment_Standard.pdf
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Promptpay
{
    /**
     * Country Code
     * TH = ไทย
     *
     * @var string
     */
    public $countryCode = 'TH';
    /**
     * Transaction Currency Code
     * 764 = บาท
     *
     * @var int
     */
    public $currencyCode = 764;
    /**
     * Merchant identifier
     *
     * @var string
     */
    public $applicationID = 'A000000677010111';
    /**
     * Transaction Amount
     *
     * @var int
     */
    private $amount;
    /**
     * ผู้รับเงิน
     * เบอร์โทร, เลขประชาชน, เลขภาษี, E-Wallet ID
     *
     * @var string
     */
    private $merchant_id;
    /**
     * รหัสประเทศของเบอร์โทรศัพท์
     * 66 = ไทย
     *
     * @var int
     */
    public $phoneCountryCode = 66;

    /**
     * Class constructor
     *
     * @param string $merchant_id เบอร์โทรศัพท์, เลข Prompt Pay หรือ e-Wallet ID
     * @param int $amount จำนวนเงิน
     */
    protected function __construct($merchant_id, $amount)
    {
        $this->merchant_id = preg_replace('/[^0-9]+/', '', trim($merchant_id));
        $this->amount = $amount;
    }

    /**
     * สร้างข้อมูลสำหรับการชำระเงินด้วย QR Code (Prompt Pay)
     * ตามมาตรฐาน EMV®
     *
     * @param string $merchant_id เบอร์โทรศัพท์, เลข Prompt Pay หรือ e-Wallet ID
     * @param int $amount จำนวนเงิน
     *
     * @return static
     */
    public static function create($merchant_id, $amount = null)
    {
        return new static($merchant_id, $amount);
    }

    /**
     * คืนค่า payload นำไปใช้สร้าง QR Code
     *
     * @return string
     */
    public function payload()
    {
        // Payload Format Indicator
        $datas = $this->format('00', 1, 2);
        // Point of Initiation method
        if ($this->amount > 0) {
            // ระบุจำนวนเงิน
            $datas .= $this->format('01', '12');
        } else {
            // ชำระแบบระบุจำนวนเงินเอง
            $datas .= $this->format('01', '11');
        }
        // Merchant identifier
        if (preg_match('/^([1-9]{0,2})0?([0-9]{9,10})$/', $this->merchant_id, $match)) {
            // Mobile number
            if ($match[1] != '') {
                $this->phoneCountryCode = $match[1];
            }
            $merchant_id = $this->format('01', sprintf('%013d', $this->phoneCountryCode.$match[2]));
        } else {
            $len = strlen($this->merchant_id);
            if ($len <= 13) {
                // National ID or Tax ID
                $merchant_id = $this->format('02', sprintf('%013d', $this->merchant_id));
            } elseif ($len <= 15) {
                // E-Wallet ID
                $merchant_id = $this->format('02', sprintf('%015d', $this->merchant_id));
            } else {
                // Bank account
                $merchant_id = $this->format('04', $this->merchant_id);
            }
        }
        $datas .= $this->format('29', $this->format('00', $this->applicationID).$merchant_id);
        // Country Code
        $datas .= $this->format('58', $this->countryCode);
        // Transaction Currency Code
        $datas .= $this->format('53', $this->currencyCode);
        if ($this->amount > 0) {
            // Transaction Amount
            $datas .= $this->format('54', number_format($this->amount, 2, '.', ''));
        }
        // Check SUM (CRC)
        $datas .= '6304';
        $datas .= $this->hashString($datas);
        // คืนค่า payload
        return $datas;
    }

    /**
     * ฟิล์ดข้อมูล
     * ID 2 หลัก ตามด้วยความยาวข้อมูล 2 หลัก และข้อมูล
     * เติม 0 ด้านหน้าข้อมูลจนครบตามที่กำหนด ถ้ามีการระบุ $length
     *
     * @param string $id
     * @param mixed $data
     *
     * @return string
     */
    private function format($id, $data, $length = 0)
    {
        if ($length > 0) {
            $data = sprintf('%0'.$length.'d', $data);
        }
        return $id.sprintf('%02d', strlen($data)).$data;
    }

    /**
     * สร้างข้อมูล Check SUM ด้วยวิธีการ CRC-16
     *
     * @param string $data
     *
     * @return string
     */
    private function hashString($data)
    {
        return strtoupper(dechex($this->hashBytes(unpack('C*', $data))));
    }

    /**
     * คำนวณ hash ด้วยวิธีการ CRC-16 (Cyclic Redundancy Check 16-bit)
     *
     * @param array $data
     *
     * @return string
     */
    private function hashBytes($data)
    {
        $Init = 0xFFFF;
        $XorOut = 0x0;
        $Array = [0x0000, 0x1021, 0x2042, 0x3063, 0x4084, 0x50A5,
            0x60C6, 0x70E7, 0x8108, 0x9129, 0xA14A, 0xB16B,
            0xC18C, 0xD1AD, 0xE1CE, 0xF1EF, 0x1231, 0x0210,
            0x3273, 0x2252, 0x52B5, 0x4294, 0x72F7, 0x62D6,
            0x9339, 0x8318, 0xB37B, 0xA35A, 0xD3BD, 0xC39C,
            0xF3FF, 0xE3DE, 0x2462, 0x3443, 0x0420, 0x1401,
            0x64E6, 0x74C7, 0x44A4, 0x5485, 0xA56A, 0xB54B,
            0x8528, 0x9509, 0xE5EE, 0xF5CF, 0xC5AC, 0xD58D,
            0x3653, 0x2672, 0x1611, 0x0630, 0x76D7, 0x66F6,
            0x5695, 0x46B4, 0xB75B, 0xA77A, 0x9719, 0x8738,
            0xF7DF, 0xE7FE, 0xD79D, 0xC7BC, 0x48C4, 0x58E5,
            0x6886, 0x78A7, 0x0840, 0x1861, 0x2802, 0x3823,
            0xC9CC, 0xD9ED, 0xE98E, 0xF9AF, 0x8948, 0x9969,
            0xA90A, 0xB92B, 0x5AF5, 0x4AD4, 0x7AB7, 0x6A96,
            0x1A71, 0x0A50, 0x3A33, 0x2A12, 0xDBFD, 0xCBDC,
            0xFBBF, 0xEB9E, 0x9B79, 0x8B58, 0xBB3B, 0xAB1A,
            0x6CA6, 0x7C87, 0x4CE4, 0x5CC5, 0x2C22, 0x3C03,
            0x0C60, 0x1C41, 0xEDAE, 0xFD8F, 0xCDEC, 0xDDCD,
            0xAD2A, 0xBD0B, 0x8D68, 0x9D49, 0x7E97, 0x6EB6,
            0x5ED5, 0x4EF4, 0x3E13, 0x2E32, 0x1E51, 0x0E70,
            0xFF9F, 0xEFBE, 0xDFDD, 0xCFFC, 0xBF1B, 0xAF3A,
            0x9F59, 0x8F78, 0x9188, 0x81A9, 0xB1CA, 0xA1EB,
            0xD10C, 0xC12D, 0xF14E, 0xE16F, 0x1080, 0x00A1,
            0x30C2, 0x20E3, 0x5004, 0x4025, 0x7046, 0x6067,
            0x83B9, 0x9398, 0xA3FB, 0xB3DA, 0xC33D, 0xD31C,
            0xE37F, 0xF35E, 0x02B1, 0x1290, 0x22F3, 0x32D2,
            0x4235, 0x5214, 0x6277, 0x7256, 0xB5EA, 0xA5CB,
            0x95A8, 0x8589, 0xF56E, 0xE54F, 0xD52C, 0xC50D,
            0x34E2, 0x24C3, 0x14A0, 0x0481, 0x7466, 0x6447,
            0x5424, 0x4405, 0xA7DB, 0xB7FA, 0x8799, 0x97B8,
            0xE75F, 0xF77E, 0xC71D, 0xD73C, 0x26D3, 0x36F2,
            0x0691, 0x16B0, 0x6657, 0x7676, 0x4615, 0x5634,
            0xD94C, 0xC96D, 0xF90E, 0xE92F, 0x99C8, 0x89E9,
            0xB98A, 0xA9AB, 0x5844, 0x4865, 0x7806, 0x6827,
            0x18C0, 0x08E1, 0x3882, 0x28A3, 0xCB7D, 0xDB5C,
            0xEB3F, 0xFB1E, 0x8BF9, 0x9BD8, 0xABBB, 0xBB9A,
            0x4A75, 0x5A54, 0x6A37, 0x7A16, 0x0AF1, 0x1AD0,
            0x2AB3, 0x3A92, 0xFD2E, 0xED0F, 0xDD6C, 0xCD4D,
            0xBDAA, 0xAD8B, 0x9DE8, 0x8DC9, 0x7C26, 0x6C07,
            0x5C64, 0x4C45, 0x3CA2, 0x2C83, 0x1CE0, 0x0CC1,
            0xEF1F, 0xFF3E, 0xCF5D, 0xDF7C, 0xAF9B, 0xBFBA,
            0x8FD9, 0x9FF8, 0x6E17, 0x7E36, 0x4E55, 0x5E74,
            0x2E93, 0x3EB2, 0x0ED1, 0x1EF0];
        $crc = $Init;
        foreach ($data as $d) {
            $crc = $Array[(($crc >> 8) ^ $d) & 0xFF] ^ ($crc << 8);
        }
        $crc = $crc ^ $XorOut;
        return $crc & 0xFFFF;
    }
}
