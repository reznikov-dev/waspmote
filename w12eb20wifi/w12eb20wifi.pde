/*
    Explanation: This is the code for Waspmote v12 with Events Board v20 and WiFi communication module

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
     
#include <WaspWIFI.h>
#include <WaspSensorEvent_v20.h>
#include <WaspFrame.h>

//Wi-Fi AP essid 
#define ESSID "33_Meshlium"
//Wi-Fi module connection socket
uint8_t socket = SOCKET0;
//Send frame parameters
char host[] = "10.10.10.1";
int  port = 80;
//request send status
uint8_t status; 

float temperature;    // Stores the temperature in ºC
float humidity;       // Stores the realitve humidity in %RH
float luminosity;     // Stores the luminosity in kOhms - resistive sensor

void setup()
{
  USB.ON();
  
  // Init Wi-Fi AP connection settings 
  if( WIFI.ON(socket) == 1 ) {    
    USB.println(F("WiFi switched ON"));
  
    /** Setinngs are already stored 
     * WIFI.setConnectionOptions(HTTP|CLIENT_SERVER);
     * WIFI.setDHCPoptions(DHCP_ON);
     * WIFI.setAuthKey(WPA1, AUTHKEY);
     * WIFI.setJoinMode(MANUAL); 
     * WIFI.storeData();
     */
  } else {
    USB.println(F("WiFi did not initialize correctly"));
  }
  
  WIFI.OFF(); 
  frame.setID("waspmote_2"); 
}


void loop()
{
  // Turn on the sensor board
  SensorEventv20.ON();
  delay(5000); //5 sec
  
  // Read sensor data
  luminosity = SensorEventv20.readValue(SENS_SOCKET2, SENS_RESISTIVE);
  temperature = SensorEventv20.readValue(SENS_SOCKET5, SENS_TEMPERATURE);
  humidity = SensorEventv20.readValue(SENS_SOCKET6, SENS_HUMIDITY);
  
  // print the values via USB for debug
  USB.println(F("***************************************"));
  USB.print(F("Temperature: "));
  USB.print(temperature);
  USB.println(F(" Celsius degrees"));
  USB.print(F("RH: "));
  USB.print(humidity);
  USB.println(F(" %"));
  USB.print(F("Luminosity: "));
  USB.print(luminosity);
  USB.println(F(" kOhms"));
  
  if( WIFI.ON(socket) == 1 ) {    
    USB.println(F("WiFi switched ON"));
    
    if (WIFI.join(ESSID))  {
      
      USB.print(F("WiFi is connected OK"));
      USB.println(F("\n----------------------"));
      USB.println(F("AP Status:"));
      WIFI.getAPstatus();
      USB.println(F("----------------------"));
      
      frame.createFrame(ASCII); 
      // add sensor fields
      frame.addSensor(SENSOR_BAT, PWR.getBatteryLevel());
      frame.addSensor(SENSOR_TCA, temperature);
      frame.addSensor(SENSOR_HUMA, humidity);
      frame.addSensor(SENSOR_LUM, luminosity);

      // print frame
      frame.showFrame();
  
       
      // Send the HTTP query with frame data
      status = WIFI.sendHTTPframe(IP, host, port, frame.buffer, frame.length);
      
      if( status == 1) {
        USB.println(F("\nHTTP query OK."));
        USB.print(F("WIFI.answer:"));
        USB.println(WIFI.answer);  
      } else {
        USB.println(F("HTTP query ERROR"));
      } 
      
    } else {
      USB.println(F("not joined"));
    }  

  } else {
    USB.println(F("WiFi did not initialize correctly"));
  }
  
  WIFI.OFF();
  SensorEventv20.OFF();
  delay(5000); //5 sec
  
  USB.println(F("Go to deep sleep mode before next mesurement for 4 minutes"));
  PWR.deepSleep("00:00:05:00", RTC_OFFSET, RTC_ALM1_MODE1, ALL_OFF);
  USB.ON();
  USB.println(F("Wake up!!\r\n"));  
}  
