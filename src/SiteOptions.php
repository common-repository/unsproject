<?php


namespace UNSProjectApp;

class SiteOptions
{
    const OPTION_NAME_CREDENTIALS = 'uns-project-credentials';
    /**
     * @var array
     */
    private $siteOption;
    /**
     * @var bool
     */
    private $shouldUpdate;

    /**
     * SiteOptions constructor.
     */
    public function __construct()
    {
        $initial = get_site_option(self::OPTION_NAME_CREDENTIALS);

        $this->shouldUpdate = $initial !== null;
        $this->siteOption = @json_decode($initial, true);
    }

    /**
     * @param $propertyName
     * @return mixed|null
     */
    public function getValue($propertyName){
        return isset($this->siteOption[$propertyName])
            ? $this->siteOption[$propertyName]
            : null;
    }

    /**
     * @return array
     */
    public function getAll(){
        return $this->siteOption;
    }

    /**
     * @param string $optionName
     * @param mixed $value
     * @return $this
     */
    public function setValue($optionName, $value){
        $this->siteOption[$optionName] = esc_html($value);

        return $this;
    }

    /**
     * @param string $optionName
     */
    public function removeValue($optionName){
        if(isset($this->siteOption[$optionName])){
            unset($this->siteOption[$optionName]);
        }
    }

    /**
     * Save option value into the Database
     */
    public function save(){
        $value = json_encode($this->siteOption);
        if($this->shouldUpdate){
            update_site_option(self::OPTION_NAME_CREDENTIALS, $value);
        }else {
            add_site_option(self::OPTION_NAME_CREDENTIALS, $value);
        }
    }

    public function resetAll()
    {
        $this->siteOption =[];
    }

}
