## Joomla 3 Virtuemart 3

### Installation

* Backup your webstore and database
* Upload the module file assetpayments-vm3-payment-plugin.zip via Extensions -> Extension Manager -> Installation -> Upload zip packet  
* Activate AssetPayments VM3 payment extension in Extensions -> Extension Manager -> Manage packets
* Create new payment method in Components -> Virtuemart -> Payment methods -> Press create button
* Configure the plugin settings (Payment methods tab):
  * Method name - Pay by card Visa/MasterCard (AssetPayments)
  * Alias - AssetPayments
  * Publish - Yes
  * Description - (optional)
  * Payment controller - AssetPayments VM3 payment extension
  * Customer group - (optional)
  * Order list - 0
  * Currency - choose your webshop currency
  * Press Save button
* Configure the plugin settings (Configuration tab):
  * Logotype - assetpayments.png
  * Merchant ID
  * Secret Key
  * Template ID - (19 by default)
  * Payment complete status
  * Payment in progress
  * Order confirmed status
  * Allowed countries - (optional)
  * Currencies - (optional)
  * Press Save & Close button
  
### Notes
Tested with Joomla 3.8.1 & Vitruemart v.3.2.4

## Joomla 3 Virtuemart 3

### Установка

* Сделайте резервную копию магазина и базы данных 
* Установите файл модуля assetpayments-vm3-payment-plugin.zip в меню Расширения -> Менеджер расширений -> Установка -> Загрузить файл пакета  
* Активируйте модулть AssetPayments VM3 payment extension в меню Расширения -> Менеджер расширений -> Управление 
* Создайте новый метод оплаты в меню Компоненты -> Virtuemart -> Методы оплаты -> Нажмите кнопку Создать
* Настройте модуль (Закладка способы оплаты):
  * Название платежа - Оплатить картой Visa/MasterCard (AssetPayments)
  * Псевдоним - AssetPayments
  * Опубликовано - Да
  * Описание платежа - (опционально)
  * Способы оплаты - AssetPayments VM3 payment extension
  * Группа покупателя - (optional)
  * Список заказов - 0
  * Валюта - выберите основную валюту магазина
  * Нажмите кнопку Сохранить 
* Настройте модуль (Закладка конфигурация):
  * Логотип - assetpayments.png
  * ID мерчанта
  * Сектретный ключ
  * ID шаблона- (19 по умолчанию)
  * Статус успешного платежа
  * Статус платежа в обработке
  * Заказ подтвержден статус
  * Доступные страны - (опционально)
  * Доступные валюты - (опционально)
  * Нажмите кнопку Сохранить и закрыть
  
### Заметки
Протестировано с Joomla 3.8.1 и Vitruemart v.3.2.4
