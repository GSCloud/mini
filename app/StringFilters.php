<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

/**
 * String Filters interface
 * 
 * Modify a string content passed by a reference to fix common problems.
 * 
 * @package GSC
 */
interface IStringFilters
{
    public static function convert_eol_to_br(&$content);
    public static function convert_eolhyphen_to_brdot(&$content);
    public static function correct_text_spacing(&$content, $language);
    public static function trim_eol(&$content);
    public static function trim_html_comment(&$content);
}

/**
 * String Filters class
 * 
 * Modify a string content passed by a reference to fix common problems.
 * 
 * @package GSC
 */
class StringFilters implements IStringFilters
{
    public static $array_replace_slovak = [
        "  " => " ",
        " % " => "&nbsp;%",
        " - " => " – ",
        " ... " => "&nbsp;… ",
        " ..." => "&nbsp;…",
        " :-(" => "&nbsp;😟",
        " :-)" => "&nbsp;🙂",
        " :-O" => "&nbsp;😮",
        " :-P" => "&nbsp;😋",
        " :-[" => "&nbsp;😕",
        " :-|" => "&nbsp;😐",
        " CZK" => "&nbsp;CZK",
        " Czk" => "&nbsp;CZK",
        " DIČ: " => " DIČ:&nbsp;",
        " EUR" => "&nbsp;EUR",
        " Eur " => "&nbsp;EUR ",
        " ID: " => " ID:&nbsp;",
        " Inc." => "&nbsp;Inc.",
        " IČ: " => " IČ:&nbsp;",
        " Kč" => "&nbsp;Kč",
        " Ltd." => "&nbsp;Ltd.",
        " USD" => "&nbsp;USD",
        " Usd" => "&nbsp;USD",
        " a " => " a&nbsp;",
        " cca. " => " cca.&nbsp;",
        " h" => "&nbsp;h",
        " h " => "&nbsp;h&nbsp;",
        " h, " => "&nbsp;h, ",
        " h. " => "&nbsp;h. ",
        " hod. " => "&nbsp;hod. ",
        " hod.)" => "&nbsp;hod.)",
        " i " => " i&nbsp;",
        " id: " => " id:&nbsp;",
        " k " => " k&nbsp;",
        " kg " => "&nbsp;kg ",
        " kg)" => "&nbsp;kg)",
        " ks " => "&nbsp;ks ",
        " ks)" => "&nbsp;ks)",
        " ks, " => "&nbsp;ks, ",
        " ks." => "&nbsp;ks.",
        " l " => "&nbsp;l ",
        " l, " => "&nbsp;l, ",
        " l. " => "&nbsp;l. ",
        " m " => "&nbsp;m ",
        " m, " => "&nbsp;m, ",
        " m. " => "&nbsp;m. ",
        " m2 " => "&nbsp;m² ",
        " m3 " => "&nbsp;m³ ",
        " mj. " => " mj.&nbsp;",
        " m² " => "&nbsp;m² ",
        " m³ " => "&nbsp;m³ ",
        " o " => " o&nbsp;",
        " p. " => " p.&nbsp;",
        " s " => " s&nbsp;",
        " s, " => "&nbsp;s, ",
        " s. " => "&nbsp;s. ",
        " s.r.o." => "&nbsp;s.r.o.",
        " sec. " => "&nbsp;sec. ",
        " sl. " => " sl.&nbsp;",
        " spol. " => "&nbsp;spol.&nbsp;",
        " str. " => " str.&nbsp;",
        " sv. " => " sv.&nbsp;",
        " tj. " => "tj.&nbsp;",
        " tzn. " => " tzn.&nbsp;",
        " tzv. " => " tzv.&nbsp;",
        " u " => " u&nbsp;",
        " v " => " v&nbsp;",
        " viz " => " viz&nbsp;",
        " z " => " z&nbsp;",
        " z. s." => "&nbsp;z.&nbsp;s.",
        " zvl. " => " zvl.&nbsp;",
        " °C " => "&nbsp;°C ",
        " °F " => "&nbsp;°F ",
        " č. " => " č.&nbsp;",
        " č. j. " => " č.&nbsp;j.&nbsp;",
        " čj. " => " čj.&nbsp;",
        " čp. " => " čp.&nbsp;",
        " čís. " => " čís.&nbsp;",
        " ‰ " => "&nbsp;‰",
        "<<" => "«",
        ">>" => "»",
    ];

    public static $array_replace_czech = [
        "  " => " ",
        " % " => "&nbsp;%",
        " - " => " – ",
        " ... " => "&nbsp;… ",
        " ..." => "&nbsp;…",
        " :-(" => "&nbsp;😟",
        " :-)" => "&nbsp;🙂",
        " :-O" => "&nbsp;😮",
        " :-P" => "&nbsp;😋",
        " :-[" => "&nbsp;😕",
        " :-|" => "&nbsp;😐",
        " CZK" => "&nbsp;CZK",
        " Czk" => "&nbsp;CZK",
        " DIČ: " => " DIČ:&nbsp;",
        " EUR" => "&nbsp;EUR",
        " Eur " => "&nbsp;EUR ",
        " ID: " => " ID:&nbsp;",
        " Inc." => "&nbsp;Inc.",
        " IČ: " => " IČ:&nbsp;",
        " Kč" => "&nbsp;Kč",
        " Ltd." => "&nbsp;Ltd.",
        " USD" => "&nbsp;USD",
        " Usd" => "&nbsp;USD",
        " a " => " a&nbsp;",
        " cca. " => " cca.&nbsp;",
        " h" => "&nbsp;h",
        " h " => "&nbsp;h&nbsp;",
        " h, " => "&nbsp;h, ",
        " h. " => "&nbsp;h. ",
        " hod. " => "&nbsp;hod. ",
        " hod.)" => "&nbsp;hod.)",
        " i " => " i&nbsp;",
        " id: " => " id:&nbsp;",
        " k " => " k&nbsp;",
        " kg " => "&nbsp;kg ",
        " kg)" => "&nbsp;kg)",
        " ks " => "&nbsp;ks ",
        " ks)" => "&nbsp;ks)",
        " ks, " => "&nbsp;ks, ",
        " ks." => "&nbsp;ks.",
        " kupř. " => " kupř.&nbsp;",
        " l " => "&nbsp;l ",
        " l, " => "&nbsp;l, ",
        " l. " => "&nbsp;l. ",
        " m " => "&nbsp;m ",
        " m, " => "&nbsp;m, ",
        " m. " => "&nbsp;m. ",
        " m2 " => "&nbsp;m² ",
        " m3 " => "&nbsp;m³ ",
        " mj. " => " mj.&nbsp;",
        " m² " => "&nbsp;m² ",
        " m³ " => "&nbsp;m³ ",
        " např. " => " např.&nbsp;",
        " o " => " o&nbsp;",
        " p. " => " p.&nbsp;",
        " popř. " => " popř.&nbsp;",
        " př. " => " př.&nbsp;",
        " přib. " => " přib.&nbsp;",
        " přibl. " => " přibl.&nbsp;",
        " s " => " s&nbsp;",
        " s, " => "&nbsp;s, ",
        " s. " => "&nbsp;s. ",
        " s.r.o." => "&nbsp;s.r.o.",
        " sec. " => "&nbsp;sec. ",
        " sl. " => " sl.&nbsp;",
        " spol. " => "&nbsp;spol.&nbsp;",
        " str. " => " str.&nbsp;",
        " sv. " => " sv.&nbsp;",
        " tj. " => "tj.&nbsp;",
        " tzn. " => " tzn.&nbsp;",
        " tzv. " => " tzv.&nbsp;",
        " tř. " => "tř.&nbsp;",
        " u " => " u&nbsp;",
        " v " => " v&nbsp;",
        " viz " => " viz&nbsp;",
        " z " => " z&nbsp;",
        " z. s." => "&nbsp;z.&nbsp;s.",
        " zvl. " => " zvl.&nbsp;",
        " °C " => "&nbsp;°C ",
        " °F " => "&nbsp;°F ",
        " č. " => " č.&nbsp;",
        " č. j. " => " č.&nbsp;j.&nbsp;",
        " čj. " => " čj.&nbsp;",
        " čp. " => " čp.&nbsp;",
        " čís. " => " čís.&nbsp;",
        " ‰ " => "&nbsp;‰",
        "<<" => "«",
        ">>" => "»",
    ];

    public static $array_replace_english = [
        "  " => " ",
        " % " => "&nbsp;% ",
        " - " => " – ",
        " ... " => "&nbsp;… ",
        " ..." => "&nbsp;…",
        " :-(" => "&nbsp;😟",
        " :-)" => "&nbsp;🙂",
        " :-O" => "&nbsp;😮",
        " :-P" => "&nbsp;😋",
        " :-[" => "&nbsp;😕",
        " :-|" => "&nbsp;😐",
        " A " => " A&nbsp;",
        " AM" => "&nbsp;AM",
        " CZK " => " CZK&nbsp;",
        " Czk " => " CZK&nbsp;",
        " EUR " => " EUR&nbsp;",
        " Eur " => " EUR&nbsp;",
        " I " => " I&nbsp;",
        " ID: " => " ID:&nbsp;",
        " Inc." => "&nbsp;Inc.",
        " Ltd." => "&nbsp;Ltd.",
        " Miss " => " Miss&nbsp;",
        " Mr " => " Mr&nbsp;",
        " Mr. " => " Mr.&nbsp;",
        " Ms " => " Ms&nbsp;",
        " Ms. " => " Ms.&nbsp;",
        " PM" => "&nbsp;PM",
        " USD " => " USD&nbsp;",
        " Usd " => " USD&nbsp;",
        " a " => " a&nbsp;",
        " h " => "&nbsp;h ",
        " h" => "&nbsp;h",
        " h, " => "&nbsp;h, ",
        " h. " => "&nbsp;h. ",
        " id: " => " id:&nbsp;",
        " kg " => "&nbsp;kg ",
        " l " => "&nbsp;l ",
        " l, " => "&nbsp;l, ",
        " l. " => "&nbsp;l. ",
        " m " => "&nbsp;m ",
        " m, " => "&nbsp;m, ",
        " m. " => "&nbsp;m. ",
        " m2 " => "&nbsp;m² ",
        " m3 " => "&nbsp;m³ ",
        " m² " => "&nbsp;m² ",
        " m³ " => "&nbsp;m³ ",
        " pcs" => "&nbsp;pcs",
        " pcs)" => "&nbsp;pcs)",
        " s " => "&nbsp;s ",
        " s, " => "&nbsp;s, ",
        " s. " => "&nbsp;s. ",
        " sec. " => "&nbsp;sec. ",
        " z. s." => "&nbsp;z.&nbsp;s.",
        " °C " => "&nbsp;°C ",
        " °F " => "&nbsp;°F ",
        " ‰ " => "&nbsp;‰",
        "<<" => "«",
        ">>" => "»",
    ];

    /**
     * Convert EOLs to <br>
     *
     * @param string $content content by reference
     * @return void
     */
    public static function convert_eol_to_br(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\n",
            "\r\n",
        ), "<br>", (string) $content);
    }

    /**
     * Convert EOL + hyphen/star to HTML
     *
     * @param string $content content by reference
     * @return void
     */
    public static function convert_eolhyphen_to_brdot(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\n* ",
            "\n- ",
            "\r\n* ",
            "\r\n- ",
        ), "<br>•&nbsp;", (string) $content);

        // fix for the beginning of the string
        if ((substr($content, 0, 2) == "- ") || (substr($content, 0, 2) == "* ")) {
            $content = "•&nbsp;" . substr($content, 2);
        }
    }

    /**
     * Trim various EOL combinations
     *
     * @param string $content content by reference
     * @return void
     */
    public static function trim_eol(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $content = str_replace(array(
            "\r\n",
            "\n",
            "\r",
        ), "", (string) $content);
    }

    /**
     * Trim THML comments
     *
     * @param string $content content by reference
     * @return void
     */
    public static function trim_html_comment(&$content)
    {
        if (!is_string($content)) {
            return;
        }

        $body = "<body";
        $c = explode($body, (string) $content, 2);
        $regex = '/<!--(.|\s)*?-->/';

        // fix only comments inside body
        if (count($c) == 2) {
            $c[1] = preg_replace($regex, "<!-- comment removed -->", $c[1]);
            $content = $c[0] . $body . $c[1];
        }

        // fix the whole string (there is no <body)
        if (count($c) == 1) {
            $content = preg_replace($regex, "<!-- comment removed -->", $content);
        }
    }

    /**
     * Correct text spacing
     * 
     * Correct the text spacing in passed content for various languages.
     *
     * @param string $content content by reference
     * @param string $language (optional: "cs", "en" - for now)
     * @return void
     */
    public static function correct_text_spacing(&$content, $language = "en")
    {
        if (!is_string($content)) {
            return $content;
        }

        $language = strtolower(trim((string) $language));
        switch ($language) {
            case "sk":
                $content = self::correct_text_spacing_sk($content);
                break;
            case "cs":
                $content = self::correct_text_spacing_cs($content);
                break;
            default:
                $content = self::correct_text_spacing_en($content);
        }
    }

    /**
     * Correct text spacing for English language
     *
     * @param string $content textual data
     * @return string
     */
    private static function correct_text_spacing_en($content = null)
    {
        if (!is_string($content)) {
            return $content;
        }

        return str_replace(array_keys(self::$array_replace_english), self::$array_replace_english, $content);
    }

    /**
     * Correct text spacing for Czech language
     *
     * @param string $content textual data
     * @return string
     */
    private static function correct_text_spacing_cs($content = null)
    {
        if (!is_string($content)) {
            return $content;
        }

        return str_replace(array_keys(self::$array_replace_czech), self::$array_replace_czech, $content);
    }

    /**
     * Correct text spacing for Slovak language
     *
     * @param string $content textual data
     * @return string
     */
    private static function correct_text_spacing_sk($content = null)
    {
        if (!is_string($content)) {
            return $content;
        }

        return str_replace(array_keys(self::$array_replace_slovak), self::$array_replace_slovak, $content);
    }
}
