/*
    Explanation: This is the code to manage, read and to send to Meshlium the temperature,
    humidity, pressure sensor (BME280) and pellistor sensor data, that includes: methane (CH4)
    and other combustible gases. Cycle time: 5 minutes.
    Hardware: Libelium - Waspmote Plug&Sense Smart Environment Pro + 9379-P and 9370-P probes

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    Version: 1.0
    Design & implementation: Vladimir Reznikov - me@reznikov.dev for http://iotsmart.ru/
*/
#include <WaspSensorGas_Pro.h>
#include <WaspWIFI_PRO.h>
#include <WaspFrame.h>

bmeGasesSensor  bme; //SOCKET_E - allowed
Gas gas(SOCKET_A);

float concentration;  // Stores the concentration level in ppm
float temperature;    // Stores the temperature in ºC
float humidity;       // Stores the realitve humidity in %RH
float pressure;       // Stores the pressure in Pa

uint8_t error;
uint8_t status;
uint8_t socket = SOCKET0;

char type[] = "http";
char host[] = "10.10.10.1";
char port[] = "80";

void setup()
{
  USB.ON();
  USB.println(F("iotsmart.ru - smart environment PRO demo"));

  frame.setID("waspmote_1");
}


void loop()
{
  // Show the remaining battery level
  USB.print(F("Battery Level: "));
  USB.print(PWR.getBatteryLevel(),DEC);
  USB.print(F(" %\r\n"));
  
  gas.ON();
  USB.println(F("Warm up gas sensor 60 sec"));

  //gas sensor warm up
  PWR.deepSleep("00:00:01:00", RTC_OFFSET, RTC_ALM1_MODE1, ALL_ON);

  bme.ON();

  // Read the pellistor sensor and compensate with the temperature internally
  concentration = gas.getConc();
  // Read enviromental variables
  temperature = bme.getTemperature();
  humidity = bme.getHumidity();
  pressure = bme.getPressure();

  // And print the values via USB for debug
  USB.println(F("***************************************"));
  USB.print(F("Gas concentration: "));
  USB.print(concentration);
  USB.println(F(" % LEL"));
  USB.print(F("Temperature: "));
  USB.print(temperature);
  USB.println(F(" Celsius degrees"));
  USB.print(F("RH: "));
  USB.print(humidity);
  USB.println(F(" %"));
  USB.print(F("Pressure: "));
  USB.print(pressure);
  USB.println(F(" Pa"));

  bme.OFF();
  gas.OFF();

  error = WIFI_PRO.ON(socket);

  if (error == 0) {    
    USB.println(F("WiFi switched ON"));
  } else {
    USB.println(F("WiFi did not initialize correctly"));
  }

  status = WIFI_PRO.isConnected();
  if (status == true) {    
    USB.print(F("WiFi is connected OK"));

    frame.createFrame(ASCII); 

    // add sensor fields
    frame.addSensor(SENSOR_BAT, PWR.getBatteryLevel());
    frame.addSensor(SENSOR_GASES_PRO_CH4, concentration);
    frame.addSensor(SENSOR_BME_TC, temperature);
    frame.addSensor(SENSOR_BME_HUM, humidity);
    frame.addSensor(SENSOR_BME_PRES, pressure);

    // print frame
    frame.showFrame();  

    ///////////////////////////////
    // 3.2. Send Frame to Meshlium
    ///////////////////////////////

    // http frame
    error = WIFI_PRO.sendFrameToMeshlium( type, host, port, frame.buffer, frame.length);

    // check response
    if (error == 0) {
      USB.println(F("HTTP OK"));             
    } else {
      USB.println(F("Error calling 'getURL' function"));
      WIFI_PRO.printErrorCode();
    }
    
  } else {
    USB.print(F("WiFi is connected ERROR")); 
  }

  // Show the remaining battery level
  USB.print(F("Battery Level: "));
  USB.print(PWR.getBatteryLevel(),DEC);
  USB.print(F(" %\r\n"));

  WIFI_PRO.OFF(socket);
  USB.println(F("WiFi switched OFF\r\n"));

  USB.println(F("Go to deep sleep mode before next mesurement for 4 minutes"));
  PWR.deepSleep("00:00:04:00", RTC_OFFSET, RTC_ALM1_MODE1, ALL_OFF);
  USB.ON();
  USB.println(F("Wake up!!\r\n"));
}
